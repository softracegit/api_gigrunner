<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_is_ok(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_docs_and_playground_are_ok(): void
    {
        $this->get('/docs')->assertOk();
        $this->get('/playground')->assertOk();
    }
}
