<?php

namespace Eae\LaravelNovaTranslatable\Tests\Feature;

use Eae\LaravelNovaTranslatable\Tests\TestCase;
use Eae\LaravelNovaTranslatable\Translatable;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Field;
use Dimsav\Translatable\Exception\LocalesNotDefinedException;

class TranslatableTest extends TestCase
{
    protected function setUp()
    {
        Translatable::defaultLocales(['en', 'pt']);
        parent::setUp();
    }

    /** @test */
    public function it_works_when_passing_no_fields_to_it()
    {
        $translatable = Translatable::make([]);

        $this->assertEquals([], $translatable->data);
    }

    /** @test */
    public function it_will_generate_a_field_per_locale()
    {
        $translatable = Translatable::make([
            new Text('title'),
        ]);

        $this->assertCount(2, $translatable->data);

        $this->assertEquals($translatable->data[0]->name, 'Title (en)');
        $this->assertEquals($translatable->data[1]->name, 'Title (pt)');
    }

    /** @test */
    public function it_accepts_a_closure_to_customize_the_label()
    {
        $translatable = Translatable::make([
            new Text('title'),
        ])->displayLocalizedNameUsing(function (Field $field, string $locale) {
            return $locale.'-'.$field->name;
        });

        $this->assertCount(2, $translatable->data);

        $this->assertEquals($translatable->data[0]->name, 'en-title');
        $this->assertEquals($translatable->data[1]->name, 'pt-title');
    }

    /** @test */
    public function it_will_can_accept_custom_locales()
    {
        $translatable = Translatable::make([
            new Text('title'),
        ])->locales(['es', 'it', 'de']);

        $this->assertCount(3, $translatable->data);

        $this->assertEquals($translatable->data[0]->name, 'Title (es)');
        $this->assertEquals($translatable->data[1]->name, 'Title (it)');
        $this->assertEquals($translatable->data[2]->name, 'Title (de)');
    }

    /** @test */
    public function it_accepts_customize_the_labels_globally()
    {
        Translatable::displayLocalizedNameByDefaultUsing(function (Field $field, string $locale) {
            return $locale.'-'.$field->name;
        });

        $translatable = Translatable::make([
            new Text('title'),
        ]);

        $this->assertCount(2, $translatable->data);

        $this->assertEquals($translatable->data[0]->name, 'en-title');
        $this->assertEquals($translatable->data[1]->name, 'pt-title');
    }

    /** @test */
    public function it_will_throw_an_exception_if_default_locales_and_no_config_are_not_set()
    {
        Translatable::defaultLocales([]);

        $this->expectException(LocalesNotDefinedException::class);

        Translatable::make([]);
    }
}