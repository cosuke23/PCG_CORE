<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every non-public route must redirect anonymous visitors to the right
 * login page. These checks run before any database query.
 */
class AccessControlTest extends TestCase
{
    public static function adminRoutes(): array
    {
        return [
            ['get', '/admin/dashboard'],
            ['post', '/admin/approve/1'],
            ['post', '/admin/reject/1'],
            ['get', '/admin/hr-analytics'],
            ['post', '/unit-users/store'],
            ['get', '/administrative-tracks'],
            ['get', '/personnel-administrative-training'],
            ['get', '/operation-tracks'],
            ['post', '/operation-tracks'],
            ['get', '/personnel/operational-training'],
            ['get', '/admin/training-applications'],
            ['get', '/admin/training-courses'],
            ['get', '/training-courses/1/students'],
            ['post', '/training-courses/1/students/import-csv'],
            ['get', '/admin/osaert'],
            ['post', '/admin/osaert/bulk-upload'],
            ['get', '/admin/organization'],
            ['get', '/admin/hrmis'],
            ['get', '/admin/personnel'],
            ['get', '/admin/personnel/create'],
            ['post', '/admin/personnel'],
            ['get', '/admin/personnel/1'],
            ['post', '/admin/personnel/1/assignments'],
            ['post', '/admin/organization'],
            ['post', '/admin/organization/1'],
            ['post', '/admin/organization/1/status'],
        ];
    }

    public static function unitRoutes(): array
    {
        return [
            ['get', '/unit/dashboard'],
            ['get', '/unit/dashboardM1'],
            ['get', '/unit/search-serial/12345'],
            ['post', '/unit/documents/review'],
            ['post', '/unit/disciplinary/store'],
            ['delete', '/unit/disciplinary/delete/1'],
            ['get', '/unit/201-files'],
            ['get', '/unit/201-files/12345'],
        ];
    }

    public static function applicantRoutes(): array
    {
        return [
            ['get', '/dashboard'],
            ['get', '/profile'],
            ['get', '/upload-documents'],
            ['post', '/documents/upload'],
            ['get', '/upload-201'],
            ['post', '/upload-201'],
            ['post', '/temp-save/step1'],
            ['post', '/reinlist/store'],
            ['get', '/generate-soi'],
            ['get', '/applicant/operational-training'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_routes_require_admin_session(string $method, string $uri)
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->$method($uri)->assertRedirect(route('admin.login'));
    }

    #[DataProvider('unitRoutes')]
    public function test_unit_routes_require_unit_session(string $method, string $uri)
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->$method($uri)->assertRedirect('/unit/login');
    }

    #[DataProvider('applicantRoutes')]
    public function test_applicant_routes_require_login(string $method, string $uri)
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->$method($uri)->assertRedirect(route('login'));
    }

    public function test_unit_json_endpoint_returns_401_for_ajax()
    {
        $this->getJson('/unit/search-serial/12345')->assertStatus(401);
    }

    public function test_security_headers_are_sent()
    {
        $this->get('/admin/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Several routes used to point at methods or views that did not exist
     * (always HTTP 500). Every controller action must exist.
     */
    public function test_every_route_points_to_an_existing_controller_method()
    {
        $missing = [];

        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();

            if (!str_contains($action, '@') || !str_starts_with($action, 'App\\')) {
                continue;
            }

            [$class, $method] = explode('@', $action);

            if (!class_exists($class) || !method_exists($class, $method)) {
                $missing[] = $route->uri() . ' -> ' . $action;
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_new_admin_pages_require_admin_session()
    {
        $this->get('/admin/unit-users')->assertRedirect(route('admin.login'));
        $this->post('/admin/unit-users/1/password')->assertRedirect(route('admin.login'));
        $this->post('/admin/unit-users/1/status')->assertRedirect(route('admin.login'));
    }
}
