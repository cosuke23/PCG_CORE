<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every non-public route must redirect anonymous visitors to the right
 * login page. These checks run before any database query.
 */
class AccessControlTest extends TestCase
{
    public function adminRoutes(): array
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
        ];
    }

    public function unitRoutes(): array
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

    public function applicantRoutes(): array
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

    /** @dataProvider adminRoutes */
    public function test_admin_routes_require_admin_session(string $method, string $uri)
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->$method($uri)->assertRedirect(route('admin.login'));
    }

    /** @dataProvider unitRoutes */
    public function test_unit_routes_require_unit_session(string $method, string $uri)
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->$method($uri)->assertRedirect('/unit/login');
    }

    /** @dataProvider applicantRoutes */
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
}
