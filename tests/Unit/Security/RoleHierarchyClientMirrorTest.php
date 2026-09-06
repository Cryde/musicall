<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The router guard has to answer "is this an admin" before the profile request comes back, so it
 * reads the JWT claim, and neither the claim nor /api/users/self is hierarchy expanded:
 * User::getRoles() returns the stored roles plus ROLE_USER and nothing else. The client therefore
 * carries its own copy of the hierarchy, and this is the contract between the two.
 *
 * The drift this catches already happened once: a super admin, stored as ["ROLE_SUPER_ADMIN"],
 * carried no ROLE_ADMIN, so the old `includes('ROLE_ADMIN')` check hid the entire back office from
 * the most privileged account on the site (#940). That role is gone (#942), and the tests below
 * keep its replacement from repeating the mistake.
 *
 * Same approach as TechRiderDuplicateNameLimitsTest and FeedbackClientMirrorTest.
 */
class RoleHierarchyClientMirrorTest extends TestCase
{
    private const string ROLES_PATH = 'assets/js/utils/roles.js';
    private const string SECURITY_PATH = 'config/packages/security.yaml';
    private const string FIXTURE_PATH = 'src/Fixtures/Factory/User/UserFactory.php';
    private const string TESTER_ROLE = 'ROLE_TESTER';

    public function test_the_client_hierarchy_matches_security_yaml(): void
    {
        $this->assertSame($this->hierarchyFromSecurityYaml(), $this->hierarchyFromClient());
    }

    /**
     * Every role named on either side of the hierarchy is exported as a constant, so the client
     * cannot refer to one by a string literal that nothing checks.
     */
    public function test_every_role_in_the_hierarchy_is_exported_by_the_client(): void
    {
        $source = $this->read(self::ROLES_PATH);

        $roles = [];
        foreach ($this->hierarchyFromSecurityYaml() as $role => $granted) {
            $roles[] = $role;
            $roles = [...$roles, ...$granted];
        }

        foreach (array_unique($roles) as $role) {
            $this->assertMatchesRegularExpression(
                sprintf("/export const [A-Z_]+ = '%s'/", preg_quote($role, '/')),
                $source,
                sprintf('%s is in the hierarchy but %s exports no constant for it.', $role, self::ROLES_PATH),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function hierarchyFromSecurityYaml(): array
    {
        $security = Yaml::parse($this->read(self::SECURITY_PATH));
        $hierarchy = $security['security']['role_hierarchy'] ?? [];
        self::assertNotEmpty($hierarchy, 'No role_hierarchy found in ' . self::SECURITY_PATH);

        $normalized = [];
        foreach ($hierarchy as $role => $granted) {
            $normalized[$role] = is_array($granted) ? array_values($granted) : [$granted];
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, list<string>>
     */
    private function hierarchyFromClient(): array
    {
        $source = $this->read(self::ROLES_PATH);

        // The GRANTS map, read as `[ROLE_X]: [ROLE_Y, ...]` entries with the constant names resolved
        // back to their values through the exports above them.
        preg_match_all("/export const ([A-Z_]+) = '([A-Z_]+)'/", $source, $constants, PREG_SET_ORDER);
        $byName = [];
        foreach ($constants as $constant) {
            $byName[$constant[1]] = $constant[2];
        }
        self::assertNotEmpty($byName, 'No role constants found in ' . self::ROLES_PATH);

        preg_match('/const GRANTS = Object\.freeze\(\{(.+?)\}\)/s', $source, $block);
        self::assertNotEmpty($block, 'No GRANTS map found in ' . self::ROLES_PATH);

        preg_match_all('/\[([A-Z_]+)\]:\s*\[([^\]]*)\]/', $block[1], $entries, PREG_SET_ORDER);
        self::assertNotEmpty($entries, 'No GRANTS entries found in ' . self::ROLES_PATH);

        $hierarchy = [];
        foreach ($entries as $entry) {
            $role = $byName[$entry[1]] ?? $entry[1];
            $granted = array_values(array_filter(array_map(
                static fn (string $name): string => $byName[trim($name)] ?? trim($name),
                explode(',', $entry[2]),
            )));
            $hierarchy[$role] = $granted;
        }
        ksort($hierarchy);

        return $hierarchy;
    }

    /**
     * The invariant #942 exists for. ROLE_TESTER is a feature flag: it reveals modules that are
     * merged but not yet announced, and grants no permission of its own. Putting it in the
     * hierarchy, in either direction, breaks that: above ROLE_ADMIN it turns every tester into an
     * admin, which is what ROLE_SUPER_ADMIN did, and below it enrols every admin into previews they
     * did not ask for.
     */
    public function test_the_tester_role_is_not_a_rank(): void
    {
        foreach ($this->hierarchyFromSecurityYaml() as $role => $granted) {
            $this->assertNotSame(self::TESTER_ROLE, $role, 'ROLE_TESTER must grant nothing.');
            $this->assertNotContains(self::TESTER_ROLE, $granted, sprintf('%s must not grant ROLE_TESTER.', $role));
        }
    }

    /**
     * ROLE_TESTER is outside the hierarchy, so the mirror above cannot vouch for it. The string
     * still has to match on both sides, or the flag silently stops working.
     */
    public function test_the_client_tester_role_matches_the_one_the_fixtures_grant(): void
    {
        $this->assertMatchesRegularExpression(
            sprintf("/export const ROLE_TESTER = '%s'/", self::TESTER_ROLE),
            $this->read(self::ROLES_PATH),
            sprintf('%s must export ROLE_TESTER as %s, or the curtain never lifts for a tester.', self::ROLES_PATH, self::TESTER_ROLE),
        );
        $this->assertStringContainsString(
            sprintf("'roles' => ['%s']", self::TESTER_ROLE),
            $this->read(self::FIXTURE_PATH),
            sprintf('%s must grant exactly %s, or dev has no account that can reach an unannounced module.', self::FIXTURE_PATH, self::TESTER_ROLE),
        );
    }

    private function read(string $relativePath): string
    {
        // tests/Unit/Security -> project root
        $path = \dirname(__DIR__, 3) . '/' . $relativePath;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
