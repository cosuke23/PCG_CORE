<?php

namespace Tests\Feature;

use Tests\TestCase;

/** PCG HRMIS main landing page (/) and the MEPCOM HRMIS home (/mepcom). */
class PublicLandingTest extends TestCase
{
    public function test_main_landing_page_is_pcg_hrmis_with_the_structure()
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>PCG HRMIS', $html);
        $this->assertStringContainsString('Find the right service, sooner.', $html);
        $this->assertStringContainsString('id="structure"', $html);
        foreach (['NHQ-PCG', 'Functional Commands', 'Admin Support Commands', 'Operational Support Commands',
            'Operating Commands', 'Special Service Commands', 'Technical Service Commands', '17 Coast Guard Districts'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('href="' . route('mepcom') . '"', $html, 'links to MEPCOM HRMIS');
        // services come before the structure
        $this->assertLessThan(strpos($html, 'id="structure"'), strpos($html, 'id="services"'));
    }

    public function test_mepcom_hrmis_home_moved_to_mepcom_and_links_back()
    {
        $html = $this->get('/mepcom')->assertOk()->getContent();
        $this->assertStringContainsString('MEPCOM HRMIS', $html);
        $this->assertStringContainsString('>PCG HRMIS</a>', $html);
        $this->assertStringNotContainsString('id="structure"', $html);
    }
}
