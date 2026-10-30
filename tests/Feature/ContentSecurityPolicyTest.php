<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function test_csp_header_uses_a_fresh_nonce_that_matches_inline_scripts()
    {
        $first = $this->get('/unit/login');
        $csp = $first->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null);

        $second = $this->get('/unit/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString($m[1], $second, 'nonce must change per request');
    }

    public function test_inline_scripts_in_pages_carry_the_nonce()
    {
        $response = $this->get('/contact'); // layouts/app has inline scripts
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);

        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $response->getContent(), $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[1] as $attrs) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $attrs);
        }
    }

    public function test_views_have_no_inline_event_handlers()
    {
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php') || str_contains($file->getFilename(), 'backup')) {
                continue;
            }
            if (preg_match('/\son(click|dblclick|change|submit|input|load|error|keyup|keydown|mouseover)\s*=/i', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Use data-on-click (public/js/pcg-ui.js) instead: CSP blocks inline handlers.');
    }

    public function test_report_only_and_off_modes()
    {
        config(['pcg.csp' => 'report']);
        $r = $this->get('/unit/login');
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertNotNull($r->headers->get('Content-Security-Policy-Report-Only'));

        config(['pcg.csp' => 'off']);
        $this->assertNull($this->get('/unit/login')->headers->get('Content-Security-Policy'));
    }
}
