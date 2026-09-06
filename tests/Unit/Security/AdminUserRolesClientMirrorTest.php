<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\ApiResource\Admin\User\AdminUser;
use App\ApiResource\Admin\User\AdminUserRoleUpdate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Count;

/**
 * The roles screen offers a toggle per grantable role, so the list exists in JavaScript as well as
 * in PHP. The API refuses anything outside its own list with a 422, so a role offered by the client
 * and unknown to the server is a switch that does nothing but produce an error.
 *
 * The reverse matters more: a role the server will grant and the client never offers is a
 * permission with no way to see or revoke it from the back office.
 *
 * Same approach as RoleHierarchyClientMirrorTest and FeedbackClientMirrorTest.
 */
class AdminUserRolesClientMirrorTest extends TestCase
{
    private const string CONSTANTS_PATH = 'assets/js/constants/adminUser.js';

    public function test_the_client_offers_exactly_the_grantable_roles(): void
    {
        preg_match_all('/value: (ROLE_[A-Z_]+)/', $this->read(), $matches);
        self::assertNotEmpty($matches[1], sprintf('No GRANTABLE_ROLES entries found in %s.', self::CONSTANTS_PATH));

        // The client refers to them through the constants exported by utils/roles.js, which are named
        // after the role they hold and whose values RoleHierarchyClientMirrorTest already pins, so
        // comparing the identifiers is enough here.
        $expected = AdminUserRoleUpdate::GRANTABLE_ROLES;
        sort($expected);
        $actual = array_values(array_unique($matches[1]));
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * ROLE_USER is added to everyone by User::getRoles(), so a toggle for it would be a switch that
     * cannot be turned off.
     */
    public function test_role_user_is_not_grantable(): void
    {
        $this->assertNotContains('ROLE_USER', AdminUserRoleUpdate::GRANTABLE_ROLES);
        $this->assertStringNotContainsString('value: ROLE_USER', $this->read());
    }

    /**
     * The count bound cannot be written as count(GRANTABLE_ROLES): an attribute argument has to be a
     * constant expression and a function call is not one. So it is a literal, and this is what stops
     * it drifting if a third role is ever added.
     */
    public function test_the_count_bound_matches_the_grantable_set(): void
    {
        $roles = new \ReflectionProperty(AdminUserRoleUpdate::class, 'roles');
        $counts = array_values(array_filter(
            $roles->getAttributes(),
            static fn (\ReflectionAttribute $attribute): bool => $attribute->getName() === Count::class,
        ));

        self::assertCount(1, $counts, 'roles must carry exactly one Assert\\Count.');
        $this->assertSame(
            count(AdminUserRoleUpdate::GRANTABLE_ROLES),
            $counts[0]->newInstance()->max,
            'The bound has to admit every grantable role and nothing beyond.',
        );
    }

    public function test_the_client_search_limits_match_the_resource(): void
    {
        $source = $this->read();

        preg_match('/export const USER_SEARCH_MIN_LENGTH = (\d+)/', $source, $minLength);
        self::assertNotEmpty($minLength, sprintf('No USER_SEARCH_MIN_LENGTH export found in %s.', self::CONSTANTS_PATH));
        $this->assertSame(AdminUser::SEARCH_MIN_LENGTH, (int) $minLength[1]);

        preg_match('/export const USER_SEARCH_ROWS_PER_PAGE = (\d+)/', $source, $rows);
        self::assertNotEmpty($rows, sprintf('No USER_SEARCH_ROWS_PER_PAGE export found in %s.', self::CONSTANTS_PATH));
        $this->assertSame(AdminUser::ITEMS_PER_PAGE, (int) $rows[1]);
    }

    private function read(): string
    {
        // tests/Unit/Security -> project root
        $path = \dirname(__DIR__, 3) . '/' . self::CONSTANTS_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
