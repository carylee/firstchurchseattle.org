<?php

declare(strict_types=1);

namespace FirstChurch\People\Tests;

use FirstChurch\People\Person;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure string/data shaping for the person directory — no WordPress. These guard
 * the bits the templates and the writer lean on (URL parsing, icon mapping,
 * tel: hrefs, pronoun normalisation).
 */
final class PersonTest extends TestCase {

	public function test_urls_splits_lines_and_drops_junk(): void {
		$blob = "https://facebook.com/fcs\n  \nnot a url\nmailto:pastor@example.org\nftp://nope";
		$this->assertSame(
			array( 'https://facebook.com/fcs', 'mailto:pastor@example.org' ),
			Person::urls( $blob )
		);
	}

	public function test_urls_preserves_author_order(): void {
		$blob = "https://instagram.com/a\nhttps://facebook.com/b";
		$this->assertSame(
			array( 'https://instagram.com/a', 'https://facebook.com/b' ),
			Person::urls( $blob )
		);
	}

	#[DataProvider( 'icons' )]
	public function test_icon_for( string $url, string $icon, string $label ): void {
		$this->assertSame( $icon, Person::iconFor( $url ) );
		$this->assertSame( $label, Person::labelFor( $url ) );
	}

	/** @return array<string,array{0:string,1:string,2:string}> */
	public static function icons(): array {
		return array(
			'facebook'      => array( 'https://www.facebook.com/firstchurch', 'facebook', 'Facebook' ),
			'instagram'     => array( 'https://instagram.com/fcs', 'instagram', 'Instagram' ),
			'twitter is x'  => array( 'https://twitter.com/fcs', 'twitter', 'X' ),
			'x.com is x'    => array( 'https://x.com/fcs', 'twitter', 'X' ),
			'youtube short' => array( 'https://youtu.be/abc', 'youtube', 'YouTube' ),
			'linkedin'      => array( 'https://www.linkedin.com/in/x', 'linkedin', 'LinkedIn' ),
			'mailto'        => array( 'mailto:a@b.org', 'email', 'Email' ),
			'unknown host'  => array( 'https://www.example.org/x', 'link', 'example.org' ),
		);
	}

	public function test_social_links_builds_render_rows(): void {
		$rows = Person::socialLinks( array( 'https://x.com/fcs', 'https://example.org/blog' ) );
		$this->assertSame(
			array(
				array( 'href' => 'https://x.com/fcs', 'icon' => 'twitter', 'label' => 'X' ),
				array( 'href' => 'https://example.org/blog', 'icon' => 'link', 'label' => 'example.org' ),
			),
			$rows
		);
	}

	public function test_social_links_accepts_a_blob(): void {
		$rows = Person::socialLinks( "https://facebook.com/a\nmailto:b@c.org" );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'facebook', $rows[0]['icon'] );
		$this->assertSame( 'email', $rows[1]['icon'] );
	}

	public function test_tel_href_keeps_digits_and_plus(): void {
		$this->assertSame( '+12066227278', Person::telHref( '+1 (206) 622-7278' ) );
		$this->assertSame( '2066227278', Person::telHref( '(206) 622-7278' ) );
	}

	public function test_tel_href_empty_when_not_diallable(): void {
		$this->assertSame( '', Person::telHref( 'ext. only' ) );
		$this->assertSame( '', Person::telHref( '+' ) );
	}

	public function test_pronouns_strips_brackets_and_collapses_space(): void {
		$this->assertSame( 'she/her', Person::pronouns( '[she/her]' ) );
		$this->assertSame( 'he/him', Person::pronouns( '  (he/him) ' ) );
		$this->assertSame( 'they/them', Person::pronouns( "they/them" ) );
		$this->assertSame( '', Person::pronouns( '   ' ) );
	}
}
