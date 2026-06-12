<?php
/**
 * Upgrade Yoast's site-wide Organization schema piece to a Church.
 *
 * Yoast represents the site as a plain Organization in its @graph. Churches
 * have their own schema.org type (Church, under PlaceOfWorship), and search
 * engines surface place details — address, service times — that Organization
 * alone can't carry. We multi-type rather than replace: the rest of Yoast's
 * graph (WebSite, WebPage) references the #organization node, so Organization
 * stays in @type and Church rides along.
 *
 * The address/phone/service-time facts below are the same ones the visit card
 * (partials/home-visit-happenings.php) and footer render — update together if
 * they ever change.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wpseo_schema_organization',
	static function ( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$types = array_filter( (array) ( $data['@type'] ?? array() ) );
		if ( ! in_array( 'Organization', $types, true ) ) {
			array_unshift( $types, 'Organization' );
		}
		if ( ! in_array( 'Church', $types, true ) ) {
			$types[] = 'Church';
		}
		$data['@type'] = array_values( $types );

		$data['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '180 Denny Way',
			'addressLocality' => 'Seattle',
			'addressRegion'   => 'WA',
			'postalCode'      => '98109',
			'addressCountry'  => 'US',
		);

		$data['telephone'] = '+1-206-622-7278';

		// Sunday worship, 10:30 am (service runs about an hour).
		$data['openingHoursSpecification'] = array(
			array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/Sunday',
				'opens'     => '10:30',
				'closes'    => '11:30',
			),
		);

		return $data;
	}
);
