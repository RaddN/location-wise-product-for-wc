<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MULOPIMFWC_Location_Resolver')) {
    /**
     * Resolve free-form customer location input into a store slug.
     */
    class MULOPIMFWC_Location_Resolver
    {
        const CACHE_GROUP = 'mulopimfwc_locations';
        const INDEX_CACHE_KEY = 'mulopimfwc_location_resolver_index_v2';
        const INDEX_TRANSIENT_KEY = 'mulopimfwc_location_resolver_index_v2';

        const DEFAULT_MIN_CONFIDENCE = 0.82;
        const DEFAULT_MIN_MARGIN = 0.07;
        const DEFAULT_MAX_GEO_RADIUS_KM = 25.0;
        const DEFAULT_CANDIDATE_LIMIT = 3;

        /**
         * Flush in-memory + transient index caches.
         *
         * @return void
         */
        public static function flush_index_cache()
        {
            wp_cache_delete(self::INDEX_CACHE_KEY, self::CACHE_GROUP);
            delete_transient(self::INDEX_TRANSIENT_KEY);
        }

        /**
         * Resolve input into a location slug.
         *
         * @param array $input
         * @param array $options
         * @return array
         */
        public function resolve_store_location(array $input, array $options = [])
        {
            $input = $this->sanitize_input($input);
            $index = $this->get_index();

            if (empty($index['locations'])) {
                return $this->build_result('not_found', null, 0.0, [], 'none');
            }

            $defaults = [
                'enable_geo' => true,
                'allow_fuzzy' => true,
                'allow_alias' => true,
                'min_confidence' => (float) apply_filters(
                    'mulopimfwc_location_resolver_min_confidence',
                    self::DEFAULT_MIN_CONFIDENCE,
                    $input
                ),
                'min_margin' => (float) apply_filters(
                    'mulopimfwc_location_resolver_min_margin',
                    self::DEFAULT_MIN_MARGIN,
                    $input
                ),
                'max_geo_radius_km' => (float) apply_filters(
                    'mulopimfwc_location_resolver_max_geo_radius_km',
                    self::DEFAULT_MAX_GEO_RADIUS_KM,
                    $input
                ),
                'candidate_limit' => (int) apply_filters(
                    'mulopimfwc_location_resolver_candidate_limit',
                    self::DEFAULT_CANDIDATE_LIMIT,
                    $input
                ),
            ];
            $options = wp_parse_args($options, $defaults);

            $options['candidate_limit'] = max(1, min(10, (int) $options['candidate_limit']));
            $options['min_confidence'] = max(0.0, min(1.0, (float) $options['min_confidence']));
            $options['min_margin'] = max(0.0, min(1.0, (float) $options['min_margin']));
            $options['max_geo_radius_km'] = max(0.0, (float) $options['max_geo_radius_km']);

            $deferred_ambiguous = null;

            if (!empty($options['allow_alias'])) {
                $direct = $this->resolve_direct_alias($input, $index, $options);
                if (is_array($direct) && isset($direct['status'])) {
                    if ($direct['status'] === 'matched') {
                        return $direct;
                    }
                    if ($direct['status'] === 'ambiguous') {
                        $deferred_ambiguous = $direct;
                    }
                }
            }

            if (!empty($options['enable_geo'])) {
                $geo = $this->resolve_geo($input, $index, $options);
                if (is_array($geo) && isset($geo['status'])) {
                    if ($geo['status'] === 'matched' || $geo['status'] === 'ambiguous') {
                        return $geo;
                    }
                }
            }

            if (!empty($options['allow_fuzzy'])) {
                $fuzzy = $this->resolve_fuzzy($input, $index, $options);
                if (is_array($fuzzy) && isset($fuzzy['status'])) {
                    return $fuzzy;
                }
            }

            if (is_array($deferred_ambiguous)) {
                return $deferred_ambiguous;
            }

            return $this->build_result('not_found', null, 0.0, [], 'none');
        }

        /**
         * Deterministic slug / alias resolution.
         *
         * @param array $input
         * @param array $index
         * @param array $options
         * @return array|null
         */
        private function resolve_direct_alias(array $input, array $index, array $options)
        {
            $query = (string) $input['query'];
            $query_slug = sanitize_title($query);

            if ($query_slug !== '' && isset($index['locations'][$query_slug])) {
                $location = $index['locations'][$query_slug];
                return $this->build_result(
                    'matched',
                    $location['slug'],
                    1.0,
                    [$this->build_candidate($location, 1.0, null)],
                    'alias'
                );
            }

            $variants = $this->build_query_variants($this->build_text_from_input($input));
            if (empty($variants)) {
                return null;
            }

            $matched_slugs = [];
            foreach ($variants as $variant) {
                if (!isset($index['alias_lookup'][$variant])) {
                    continue;
                }
                foreach ($index['alias_lookup'][$variant] as $slug) {
                    if (isset($index['locations'][$slug])) {
                        $matched_slugs[$slug] = true;
                    }
                }
            }

            if (empty($matched_slugs)) {
                return null;
            }

            $scored = [];
            foreach (array_keys($matched_slugs) as $slug) {
                $location = $index['locations'][$slug];
                $scored[] = [
                    'location' => $location,
                    'score' => 1.0,
                ];
            }
            $this->sort_scored_locations($scored);

            if (count($scored) === 1) {
                $location = $scored[0]['location'];
                return $this->build_result(
                    'matched',
                    $location['slug'],
                    1.0,
                    [$this->build_candidate($location, 1.0, null)],
                    'alias'
                );
            }

            $candidates = $this->build_candidates_from_scored($scored, (int) $options['candidate_limit']);

            return $this->build_result(
                'ambiguous',
                null,
                1.0,
                $candidates,
                'none'
            );
        }

        /**
         * Geo-based resolution from coordinates.
         *
         * @param array $input
         * @param array $index
         * @param array $options
         * @return array|null
         */
        private function resolve_geo(array $input, array $index, array $options)
        {
            if (!isset($input['lat'], $input['lng']) || $input['lat'] === null || $input['lng'] === null) {
                return null;
            }

            $lat = (float) $input['lat'];
            $lng = (float) $input['lng'];
            $radius = max(0.1, (float) $options['max_geo_radius_km']);

            $scored = [];
            foreach ($index['locations'] as $location) {
                if ($location['lat'] === null || $location['lng'] === null) {
                    continue;
                }

                $distance = $this->calculate_haversine_distance_km(
                    $lat,
                    $lng,
                    (float) $location['lat'],
                    (float) $location['lng']
                );

                if ($distance > $radius) {
                    continue;
                }

                $distance_score = max(0.0, 1.0 - ($distance / $radius));
                $base_score = 0.40 + (0.60 * $distance_score);

                $text = $this->build_text_from_input($input);
                if ($text !== '') {
                    $query_variants = $this->build_query_variants($text);
                    $boost = 0.0;
                    foreach ($query_variants as $query_variant) {
                        foreach ($location['search_variants'] as $search_variant) {
                            $boost = max($boost, $this->similarity_score($query_variant, $search_variant));
                        }
                    }
                    $base_score = min(1.0, $base_score + (0.08 * $boost));
                }

                $scored[] = [
                    'location' => $location,
                    'score' => $base_score,
                    'distance_km' => $distance,
                ];
            }

            if (empty($scored)) {
                return null;
            }

            usort($scored, function ($a, $b) {
                $score_cmp = $b['score'] <=> $a['score'];
                if ($score_cmp !== 0) {
                    return $score_cmp;
                }

                $distance_cmp = $a['distance_km'] <=> $b['distance_km'];
                if ($distance_cmp !== 0) {
                    return $distance_cmp;
                }

                $order_cmp = (int) $a['location']['display_order'] <=> (int) $b['location']['display_order'];
                if ($order_cmp !== 0) {
                    return $order_cmp;
                }

                return strcmp($a['location']['slug'], $b['location']['slug']);
            });

            $top = $scored[0];
            $second = $scored[1] ?? null;
            $top_score = (float) $top['score'];
            $second_score = $second ? (float) $second['score'] : 0.0;
            $distance_gap = $second ? max(0.0, (float) $second['distance_km'] - (float) $top['distance_km']) : $radius;
            $gap_boost = min(0.12, ($distance_gap / max(1.0, $radius)) * 0.20);
            $confidence = min(1.0, $top_score + $gap_boost);
            $margin = max(0.0, $confidence - $second_score);

            $candidates = $this->build_candidates_from_scored($scored, (int) $options['candidate_limit']);

            // If only one active store is in range and it is reasonably close, trust geo match.
            if (count($scored) === 1) {
                $single_distance_limit = max(3.0, $radius * 0.75);
                if ((float) $top['distance_km'] <= $single_distance_limit) {
                    return $this->build_result(
                        'matched',
                        $top['location']['slug'],
                        max($confidence, 0.86),
                        $candidates,
                        'geo'
                    );
                }
            }

            if ($confidence >= (float) $options['min_confidence'] && $margin >= (float) $options['min_margin']) {
                return $this->build_result(
                    'matched',
                    $top['location']['slug'],
                    $confidence,
                    $candidates,
                    'geo'
                );
            }

            return $this->build_result(
                'ambiguous',
                null,
                $confidence,
                $candidates,
                'none'
            );
        }

        /**
         * Fuzzy text-based resolution.
         *
         * @param array $input
         * @param array $index
         * @param array $options
         * @return array|null
         */
        private function resolve_fuzzy(array $input, array $index, array $options)
        {
            $text = $this->build_text_from_input($input);
            if ($text === '') {
                return null;
            }

            $query_variants = $this->build_query_variants($text);
            if (empty($query_variants)) {
                return null;
            }

            $scored = [];
            foreach ($index['locations'] as $location) {
                $best = 0.0;
                foreach ($query_variants as $query_variant) {
                    foreach ($location['search_variants'] as $search_variant) {
                        $best = max($best, $this->similarity_score($query_variant, $search_variant));
                        if ($best >= 1.0) {
                            break 2;
                        }
                    }
                }

                if ($best < 0.05) {
                    continue;
                }

                if (isset($input['lat'], $input['lng']) && $input['lat'] !== null && $input['lng'] !== null && $location['lat'] !== null && $location['lng'] !== null) {
                    $distance = $this->calculate_haversine_distance_km(
                        (float) $input['lat'],
                        (float) $input['lng'],
                        (float) $location['lat'],
                        (float) $location['lng']
                    );
                    $geo_bonus = max(0.0, 1.0 - min($distance, 50.0) / 50.0) * 0.06;
                    $best = min(1.0, $best + $geo_bonus);
                }

                $scored[] = [
                    'location' => $location,
                    'score' => $best,
                ];
            }

            if (empty($scored)) {
                return $this->build_result('not_found', null, 0.0, [], 'none');
            }

            $this->sort_scored_locations($scored);

            $top = $scored[0];
            $second = $scored[1] ?? null;
            $top_score = (float) $top['score'];
            $second_score = $second ? (float) $second['score'] : 0.0;
            $margin = max(0.0, $top_score - $second_score);
            $candidates = $this->build_candidates_from_scored($scored, (int) $options['candidate_limit']);

            if ($top_score >= (float) $options['min_confidence'] && $margin >= (float) $options['min_margin']) {
                return $this->build_result(
                    'matched',
                    $top['location']['slug'],
                    $top_score,
                    $candidates,
                    'fuzzy'
                );
            }

            // Secondary guardrail: allow high-quality fuzzy hit with clearly better margin.
            if ($top_score >= 0.72 && $margin >= max(0.15, (float) $options['min_margin'])) {
                return $this->build_result(
                    'matched',
                    $top['location']['slug'],
                    $top_score,
                    $candidates,
                    'fuzzy'
                );
            }

            if ($top_score >= max(0.25, (float) $options['min_confidence'] - 0.35)) {
                return $this->build_result(
                    'ambiguous',
                    null,
                    $top_score,
                    $candidates,
                    'none'
                );
            }

            return $this->build_result('not_found', null, $top_score, $candidates, 'none');
        }

        /**
         * Build normalized result payload.
         *
         * @param string $status
         * @param string|null $slug
         * @param float $confidence
         * @param array $candidates
         * @param string $reason
         * @return array
         */
        private function build_result($status, $slug, $confidence, array $candidates, $reason)
        {
            return [
                'status' => $status,
                'slug' => $slug ? (string) $slug : null,
                'confidence' => round(max(0.0, min(1.0, (float) $confidence)), 4),
                'candidates' => $candidates,
                'reason' => $reason ?: 'none',
            ];
        }

        /**
         * Build candidate payload from scored list.
         *
         * @param array $scored
         * @param int $limit
         * @return array
         */
        private function build_candidates_from_scored(array $scored, $limit)
        {
            $limit = max(1, (int) $limit);
            $candidates = [];
            $count = 0;

            foreach ($scored as $row) {
                $candidates[] = $this->build_candidate(
                    $row['location'],
                    (float) $row['score'],
                    isset($row['distance_km']) ? (float) $row['distance_km'] : null
                );
                $count++;
                if ($count >= $limit) {
                    break;
                }
            }

            return $candidates;
        }

        /**
         * Build a candidate structure.
         *
         * @param array $location
         * @param float $score
         * @param float|null $distance_km
         * @return array
         */
        private function build_candidate(array $location, $score, $distance_km = null)
        {
            $candidate = [
                'slug' => (string) $location['slug'],
                'name' => (string) $location['name'],
                'confidence' => round(max(0.0, min(1.0, (float) $score)), 4),
            ];

            if ($distance_km !== null) {
                $candidate['distance_km'] = round(max(0.0, (float) $distance_km), 3);
            }

            return $candidate;
        }

        /**
         * Sort scored locations by confidence + stable tie-breakers.
         *
         * @param array $scored
         * @return void
         */
        private function sort_scored_locations(array &$scored)
        {
            usort($scored, function ($a, $b) {
                $score_cmp = (float) $b['score'] <=> (float) $a['score'];
                if ($score_cmp !== 0) {
                    return $score_cmp;
                }

                $order_cmp = (int) $a['location']['display_order'] <=> (int) $b['location']['display_order'];
                if ($order_cmp !== 0) {
                    return $order_cmp;
                }

                return strcmp($a['location']['slug'], $b['location']['slug']);
            });
        }

        /**
         * Build merged text from input fields.
         *
         * @param array $input
         * @return string
         */
        private function build_text_from_input(array $input)
        {
            $query = isset($input['query']) ? trim((string) $input['query']) : '';
            if ($query !== '') {
                return $query;
            }

            $parts = [];
            foreach (['address', 'city', 'state', 'country'] as $key) {
                if (!isset($input[$key])) {
                    continue;
                }
                $value = trim((string) $input[$key]);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }

            if (empty($parts)) {
                return '';
            }

            $parts = array_values(array_unique($parts));

            return implode(' ', $parts);
        }

        /**
         * Sanitize raw resolver input.
         *
         * @param array $input
         * @return array
         */
        private function sanitize_input(array $input)
        {
            $result = [
                'query' => isset($input['query']) ? sanitize_text_field((string) $input['query']) : '',
                'address' => isset($input['address']) ? sanitize_text_field((string) $input['address']) : '',
                'city' => isset($input['city']) ? sanitize_text_field((string) $input['city']) : '',
                'state' => isset($input['state']) ? sanitize_text_field((string) $input['state']) : '',
                'country' => isset($input['country']) ? sanitize_text_field((string) $input['country']) : '',
                'lat' => null,
                'lng' => null,
            ];

            if (isset($input['lat']) && is_numeric($input['lat'])) {
                $lat = (float) $input['lat'];
                if ($lat >= -90.0 && $lat <= 90.0) {
                    $result['lat'] = $lat;
                }
            }

            if (isset($input['lng']) && is_numeric($input['lng'])) {
                $lng = (float) $input['lng'];
                if ($lng >= -180.0 && $lng <= 180.0) {
                    $result['lng'] = $lng;
                }
            }

            return $result;
        }

        /**
         * Return indexed active locations.
         *
         * @return array
         */
        private function get_index()
        {
            $cached = wp_cache_get(self::INDEX_CACHE_KEY, self::CACHE_GROUP);
            if (is_array($cached) && isset($cached['locations'], $cached['alias_lookup'])) {
                return $cached;
            }

            $transient = get_transient(self::INDEX_TRANSIENT_KEY);
            if (is_array($transient) && isset($transient['locations'], $transient['alias_lookup'])) {
                wp_cache_set(self::INDEX_CACHE_KEY, $transient, self::CACHE_GROUP, HOUR_IN_SECONDS);
                return $transient;
            }

            $index = $this->build_index();
            wp_cache_set(self::INDEX_CACHE_KEY, $index, self::CACHE_GROUP, HOUR_IN_SECONDS);
            set_transient(self::INDEX_TRANSIENT_KEY, $index, HOUR_IN_SECONDS);

            return $index;
        }

        /**
         * Build in-memory index of active locations + aliases.
         *
         * @return array
         */
        private function build_index()
        {
            if (function_exists('mulopimfwc_get_frontend_locations')) {
                $locations = mulopimfwc_get_frontend_locations();
            } else {
                $locations = get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                ]);
            }

            if (is_wp_error($locations) || empty($locations)) {
                return [
                    'locations' => [],
                    'alias_lookup' => [],
                ];
            }

            $index = [
                'locations' => [],
                'alias_lookup' => [],
            ];

            foreach ($locations as $location) {
                if (!isset($location->term_id, $location->slug)) {
                    continue;
                }

                $term_id = (int) $location->term_id;
                $slug = sanitize_title((string) $location->slug);
                if ($slug === '') {
                    continue;
                }

                $display_order = get_term_meta($term_id, 'display_order', true);
                $display_order = $display_order !== '' ? (int) $display_order : 999;

                $lat = get_term_meta($term_id, 'latitude', true);
                $lng = get_term_meta($term_id, 'longitude', true);
                $lat = is_numeric($lat) ? (float) $lat : null;
                $lng = is_numeric($lng) ? (float) $lng : null;
                if ($lat !== null && ($lat < -90.0 || $lat > 90.0)) {
                    $lat = null;
                }
                if ($lng !== null && ($lng < -180.0 || $lng > 180.0)) {
                    $lng = null;
                }

                $street = sanitize_text_field((string) get_term_meta($term_id, 'street_address', true));
                $city = sanitize_text_field((string) get_term_meta($term_id, 'city', true));
                $state = sanitize_text_field((string) get_term_meta($term_id, 'state', true));
                $country = sanitize_text_field((string) get_term_meta($term_id, 'country', true));

                $aliases = $this->collect_location_aliases($location, [
                    'street' => $street,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                ]);

                $search_texts = $aliases;
                if ($street !== '') {
                    $search_texts[] = $street;
                }
                if ($city !== '') {
                    $search_texts[] = $city;
                }
                if ($state !== '') {
                    $search_texts[] = $state;
                }
                if ($country !== '') {
                    $search_texts[] = $country;
                }
                if ($street !== '' && $city !== '') {
                    $search_texts[] = $street . ' ' . $city;
                }
                if ($city !== '' && $state !== '') {
                    $search_texts[] = $city . ' ' . $state;
                }
                if ($city !== '' && $country !== '') {
                    $search_texts[] = $city . ' ' . $country;
                }

                $normalized_aliases = [];
                $search_variants = [];
                foreach (array_values(array_unique(array_filter($search_texts))) as $text) {
                    foreach ($this->build_query_variants($text) as $variant) {
                        $search_variants[$variant] = true;
                        if (in_array($text, $aliases, true)) {
                            $normalized_aliases[$variant] = true;
                            if (!isset($index['alias_lookup'][$variant])) {
                                $index['alias_lookup'][$variant] = [];
                            }
                            if (!in_array($slug, $index['alias_lookup'][$variant], true)) {
                                $index['alias_lookup'][$variant][] = $slug;
                            }
                        }
                    }
                }

                $index['locations'][$slug] = [
                    'term_id' => $term_id,
                    'slug' => $slug,
                    'name' => sanitize_text_field((string) $location->name),
                    'display_order' => $display_order,
                    'lat' => $lat,
                    'lng' => $lng,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                    'search_variants' => array_keys($search_variants),
                    'normalized_aliases' => array_keys($normalized_aliases),
                ];
            }

            return $index;
        }

        /**
         * Collect built-in and term-meta aliases.
         *
         * @param WP_Term $location
         * @param array $address
         * @return array
         */
        private function collect_location_aliases($location, array $address)
        {
            $aliases = [];

            $slug = sanitize_title((string) $location->slug);
            $name = sanitize_text_field((string) $location->name);

            if ($slug !== '') {
                $aliases[] = $slug;
                $aliases[] = str_replace(['-', '_'], ' ', $slug);
                $aliases[] = str_replace(['-', '_'], '', $slug);
            }

            if ($name !== '') {
                $aliases[] = $name;
            }

            $meta_aliases = $this->parse_alias_meta(get_term_meta((int) $location->term_id, 'location_aliases', true));
            if (!empty($meta_aliases)) {
                $aliases = array_merge($aliases, $meta_aliases);
            }

            $aliases = array_map(function ($value) {
                return sanitize_text_field((string) $value);
            }, $aliases);

            $aliases = array_values(array_filter(array_unique($aliases), function ($value) {
                return trim($value) !== '';
            }));

            return $aliases;
        }

        /**
         * Parse aliases from term meta (array/json/string).
         *
         * @param mixed $raw
         * @return array
         */
        private function parse_alias_meta($raw)
        {
            if (is_array($raw)) {
                $flattened = [];
                array_walk_recursive($raw, function ($value) use (&$flattened) {
                    if (is_scalar($value)) {
                        $flattened[] = (string) $value;
                    }
                });
                return array_values(array_filter($flattened));
            }

            if (!is_string($raw)) {
                return [];
            }

            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->parse_alias_meta($decoded);
            }

            $parts = preg_split('/[\r\n,;|]+/', $raw);
            if (!is_array($parts)) {
                return [];
            }

            return array_values(array_filter(array_map('trim', $parts), function ($value) {
                return $value !== '';
            }));
        }

        /**
         * Build normalized variants used for alias and fuzzy matching.
         *
         * @param string $text
         * @return array
         */
        private function build_query_variants($text)
        {
            $text = trim((string) $text);
            if ($text === '') {
                return [];
            }

            $variants = [];

            $normalized = $this->normalize_text($text);
            if ($normalized !== '') {
                $variants[] = $normalized;
                $compact = $this->normalize_compact($normalized);
                if ($compact !== '' && $compact !== $normalized) {
                    $variants[] = $compact;
                }
            }

            $slug = sanitize_title($text);
            if ($slug !== '') {
                $slug_spaced = $this->normalize_text(str_replace(['-', '_'], ' ', $slug));
                if ($slug_spaced !== '') {
                    $variants[] = $slug_spaced;
                }
                $slug_compact = $this->normalize_compact($slug);
                if ($slug_compact !== '') {
                    $variants[] = $slug_compact;
                }
            }

            $transliterated = $this->maybe_transliterate($text);
            if ($transliterated !== '' && $transliterated !== $text) {
                $trans_norm = $this->normalize_text($transliterated);
                if ($trans_norm !== '') {
                    $variants[] = $trans_norm;
                }
                $trans_compact = $this->normalize_compact($transliterated);
                if ($trans_compact !== '') {
                    $variants[] = $trans_compact;
                }
            }

            return array_values(array_unique(array_filter($variants)));
        }

        /**
         * Normalize text while keeping multilingual letters.
         *
         * @param string $value
         * @return string
         */
        private function normalize_text($value)
        {
            $value = wp_strip_all_tags((string) $value);
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $value = remove_accents($value);
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            if (function_exists('mb_strtolower')) {
                $value = mb_strtolower($value, 'UTF-8');
            } else {
                $value = strtolower($value);
            }

            $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
            $value = preg_replace('/\s+/u', ' ', (string) $value);

            return trim((string) $value);
        }

        /**
         * Compact normalized text by removing separators.
         *
         * @param string $value
         * @return string
         */
        private function normalize_compact($value)
        {
            $value = $this->normalize_text((string) $value);
            if ($value === '') {
                return '';
            }

            $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
            return trim((string) $compact);
        }

        /**
         * Attempt transliteration for non-Latin input.
         *
         * @param string $value
         * @return string
         */
        private function maybe_transliterate($value)
        {
            $value = (string) $value;
            if ($value === '' || !function_exists('iconv')) {
                return '';
            }

            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (!is_string($converted)) {
                return '';
            }

            $converted = trim($converted);
            if ($converted === '') {
                return '';
            }

            return $converted;
        }

        /**
         * Combined similarity score for fuzzy matching.
         *
         * @param string $left
         * @param string $right
         * @return float
         */
        private function similarity_score($left, $right)
        {
            $left = trim((string) $left);
            $right = trim((string) $right);

            if ($left === '' || $right === '') {
                return 0.0;
            }

            if ($left === $right) {
                return 1.0;
            }

            $containment = 0.0;
            if (strpos($left, $right) !== false || strpos($right, $left) !== false) {
                $short = min($this->str_len($left), $this->str_len($right));
                $long = max($this->str_len($left), $this->str_len($right), 1);
                $ratio = $short / $long;
                $containment = min(0.95, max(0.0, ($ratio * 0.92) + 0.03));
            }

            $percent = 0.0;
            similar_text($left, $right, $percent);
            $similarity = max(0.0, min(1.0, $percent / 100.0));

            $lev_score = 0.0;
            $left_len = $this->str_len($left);
            $right_len = $this->str_len($right);
            if (function_exists('levenshtein') && $left_len > 0 && $right_len > 0 && $left_len <= 255 && $right_len <= 255) {
                $distance = levenshtein($left, $right);
                $denominator = max($left_len, $right_len, 1);
                $lev_score = max(0.0, 1.0 - ($distance / $denominator));
            }

            $token_score = $this->token_similarity($left, $right);

            return max($containment, $similarity, $lev_score, $token_score);
        }

        /**
         * Token-based overlap score.
         *
         * @param string $left
         * @param string $right
         * @return float
         */
        private function token_similarity($left, $right)
        {
            $tokens_left = array_values(array_filter(array_unique(explode(' ', $left))));
            $tokens_right = array_values(array_filter(array_unique(explode(' ', $right))));

            if (empty($tokens_left) || empty($tokens_right)) {
                return 0.0;
            }

            $intersection = array_intersect($tokens_left, $tokens_right);
            $intersection_count = count($intersection);
            if ($intersection_count === 0) {
                return 0.0;
            }

            $union_count = count(array_unique(array_merge($tokens_left, $tokens_right)));
            $jaccard = $union_count > 0 ? ($intersection_count / $union_count) : 0.0;
            $containment = $intersection_count / max(count($tokens_left), count($tokens_right), 1);

            return max($jaccard, $containment * 0.9);
        }

        /**
         * Multibyte-safe length.
         *
         * @param string $value
         * @return int
         */
        private function str_len($value)
        {
            if (function_exists('mb_strlen')) {
                return (int) mb_strlen((string) $value, 'UTF-8');
            }

            return (int) strlen((string) $value);
        }

        /**
         * Haversine distance in kilometers.
         *
         * @param float $lat1
         * @param float $lng1
         * @param float $lat2
         * @param float $lng2
         * @return float
         */
        private function calculate_haversine_distance_km($lat1, $lng1, $lat2, $lng2)
        {
            $earth_radius = 6371.0;

            $lat1_rad = deg2rad((float) $lat1);
            $lat2_rad = deg2rad((float) $lat2);
            $delta_lat = deg2rad((float) $lat2 - (float) $lat1);
            $delta_lng = deg2rad((float) $lng2 - (float) $lng1);

            $a = sin($delta_lat / 2) ** 2
                + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lng / 2) ** 2;

            $c = 2 * asin(min(1, sqrt($a)));

            return $earth_radius * $c;
        }
    }
}
