<?php

namespace WeDevs\WeDocs;

/**
 * Shortcode.
 */
class Shortcode {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_shortcode( 'wedocs', [ $this, 'shortcode' ] );
    }

    /**
     * Shortcode handler.
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public function shortcode( $atts, $content = '' ) {
        Frontend::enqueue_assets();

        ob_start();
        self::wedocs( $atts );
        $content .= ob_get_clean();

        return $content;
    }

    /**
     * Generic function for displaying docs.
     *
     * @param array $args
     *
     * @return void
     */
    public static function wedocs( $args = [] ) {
        $defaults = [
            'col'     => '2',
            'include' => 'any',
            'exclude' => '',
            'items'   => 10,
            'more'    => __( 'View Details', 'wedocs' ),
			'tag'	  => '',
        ];

        $args     = wp_parse_args( $args, $defaults );
        $arranged = [];

		$parent_args = [
			'post_type'   => 'docs',
			'post_parent' => 0,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'numberposts' => -1, // get all, alternatively you can limit it
		];

		if ( 'any' != $args['include'] ) {
			$parent_args['include'] = explode(',', $args['include']); // Convert string to array
		}

		if ( !empty( $args['exclude'] ) ) {
			$parent_args['exclude'] = explode(',', $args['exclude']); // Convert string to array
		}

		if ( !empty( $args['tag'] ) ) {
			$tags = strpos($args['tag'], ',') !== false ? explode(',', $args['tag']) : array($args['tag']);
			$parent_args['tax_query'] = [
				[
					'taxonomy' => 'doc_tag',
					'field'    => 'slug',
					'terms'    => $tags,
				]
			];
		}

		$parent_docs = get_posts( $parent_args );


		// arrange the docs
		if ( $parent_docs ) {
			foreach ( $parent_docs as $root ) {
				$sections_args = [
					'post_parent'    => $root->ID,
					'post_type'      => 'docs',
					'post_status'    => 'publish',
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
					'numberposts'    => (int) $args['items'],
				];

				$tags = !empty($args['tag']) ? (strpos($args['tag'], ',') !== false ? explode(',', $args['tag']) : array($args['tag'])) : [];

				if ( !empty($tags) ) {
					$sections_args['tax_query'] = [
						[
							'taxonomy' => 'doc_tag',
							'field'    => 'slug',
							'terms'    => $tags,
						]
					];
				}

				$sections = get_posts( $sections_args );

				// Check if the parent doc or any of its sections has the tag
				$parent_or_section_has_tag = !empty($tags) ? has_term( $tags, 'doc_tag', $root->ID ) : false;

				foreach ($sections as $section) {
					if (has_term( $tags, 'doc_tag', $section->ID )) {
						$parent_or_section_has_tag = true;
						break;
					}
				}

				// Only add parent doc to $arranged array if it or one of its sections has the tag
				if ( !empty($tags) ? $parent_or_section_has_tag : true ) {
					$arranged[] = [
						'doc'      => $root,
						'sections' => $sections,
					];
				}
			}
		}


        // call the template
        wedocs_get_template( 'shortcode.php', [
            'docs' => $arranged,
            'col'  => (int) $args['col'],
            'more' => $args['more'],
        ] );
    }
}
