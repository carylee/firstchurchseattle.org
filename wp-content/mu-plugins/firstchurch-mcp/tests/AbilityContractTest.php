<?php
/**
 * Tier 1 — contract/meta-tests over the *live* registered ability surface.
 *
 * One sweep over everything the mu-plugin registers, asserting each ability is
 * structurally sound (schema, permission callback, MCP visibility) so a typo or
 * malformed schema in any of the ~46 abilities fails CI immediately.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbilityContractTest extends TestCase
{
    /** @return array<string,array{0:string,1:array<string,mixed>}> */
    public static function abilityProvider(): array
    {
        $out = array();
        foreach (fcmcp_test_boot_abilities() as $name => $args) {
            $out[$name] = array($name, $args);
        }
        return $out;
    }

    public function testSomeAbilitiesAreRegistered(): void
    {
        $this->assertNotEmpty(fcmcp_test_boot_abilities(), 'Expected the mu-plugin to register abilities.');
    }

    #[DataProvider('abilityProvider')]
    public function testAbilityHasRequiredMetadata(string $name, array $args): void
    {
        $this->assertNotEmpty($args['label'] ?? '', "$name is missing a label.");
        $this->assertNotEmpty($args['description'] ?? '', "$name is missing a description.");
        $this->assertSame('firstchurch', $args['category'] ?? null, "$name is not in the firstchurch category.");
    }

    #[DataProvider('abilityProvider')]
    public function testAbilityCallbacksAreCallable(string $name, array $args): void
    {
        $this->assertArrayHasKey('execute_callback', $args, "$name has no execute_callback.");
        $this->assertTrue(
            is_callable($args['execute_callback']),
            "$name execute_callback is not callable (a string callback may name a missing function)."
        );

        $this->assertArrayHasKey('permission_callback', $args, "$name has no permission_callback.");
        $this->assertTrue(is_callable($args['permission_callback']), "$name permission_callback is not callable.");
    }

    #[DataProvider('abilityProvider')]
    public function testAbilityIsExposedToMcp(string $name, array $args): void
    {
        $this->assertTrue(
            ($args['meta']['mcp']['public'] ?? false) === true,
            "$name is not flagged mcp.public, so the adapter won't expose it."
        );
    }

    #[DataProvider('abilityProvider')]
    public function testInputSchemaIsWellFormed(string $name, array $args): void
    {
        if (!isset($args['input_schema'])) {
            $this->assertTrue(true); // Schema is optional (e.g. list-* abilities).
            return;
        }
        $schema = $args['input_schema'];
        $this->assertIsArray($schema, "$name input_schema must be an array.");
        $this->assertSame('object', $schema['type'] ?? null, "$name input_schema must be type=object.");
        $this->assertArrayHasKey('additionalProperties', $schema, "$name input_schema must declare additionalProperties.");
        $this->assertFalse($schema['additionalProperties'], "$name input_schema should set additionalProperties=false.");

        $properties = $schema['properties'] ?? array();
        $this->assertIsArray($properties, "$name input_schema properties must be an array.");

        foreach (($schema['required'] ?? array()) as $req) {
            $this->assertArrayHasKey(
                $req,
                $properties,
                "$name lists '$req' as required but it is not defined in properties."
            );
        }
    }

    #[DataProvider('abilityProvider')]
    public function testWriteAbilitiesDeclareDestructiveness(string $name, array $args): void
    {
        $annotations = $args['meta']['annotations'] ?? null;
        if (null === $annotations) {
            $this->assertTrue(true); // Annotations are advisory; not every ability sets them.
            return;
        }
        $this->assertIsArray($annotations, "$name annotations must be an array.");
        foreach (array('readonly', 'destructive', 'idempotent') as $flag) {
            if (array_key_exists($flag, $annotations)) {
                $this->assertIsBool($annotations[$flag], "$name annotation '$flag' must be boolean.");
            }
        }
    }
}
