<?php

namespace Tests\Feature;

use Tests\TestCase;

class WorkFormModalTest extends TestCase
{
    public function test_completion_fallback_only_notifies_the_parent_after_an_unwarned_success(): void
    {
        $redirectUrl = '/?progress=Listening#RJ000000001';
        $completed = view('WorkFormCompleted', compact('redirectUrl'))->render();

        $this->assertStringContainsString('target="_top"', $completed);
        $this->assertStringContainsString('window.parent.postMessage({', $completed);
        $this->assertStringContainsString("type: 'work-form-completed'", $completed);
        $this->assertStringContainsString('window.location.origin', $completed);

        $warning = 'Saved, but an image could not be downloaded.';
        $warned = view('WorkFormCompleted', compact('redirectUrl', 'warning'))->render();

        $this->assertStringContainsString('role="alert"', $warned);
        $this->assertStringContainsString($warning, $warned);
        $this->assertStringContainsString('target="_top"', $warned);
        $this->assertStringNotContainsString('window.parent.postMessage({', $warned);
    }
}
