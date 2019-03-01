<?php

namespace Eae\LaravelNovaTranslatable;

use Closure;
use Dimsav\Translatable\Exception\LocalesNotDefinedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Controllers\ResourceIndexController;

class Translatable extends MergeValue
{

    /** @var string[] */
    protected static $defaultLocales = [];

    /** @var \Laravel\Nova\Fields\Field[] */
    protected $originalFields;

    /** @var  string[] */
    protected $locales = [];

    /** @var \Closure|null */
    protected static $displayLocalizedNameByDefaultUsingCallback;

    /** @var \Closure */
    protected $displayLocalizedNameUsingCallback;

    /**
     * The field's assigned panel.
     *
     * @var string
     */
    public $panel;

    public static function make(array $fields): self
    {
        return new static($fields);
    }

    public function __construct(array $fields = [])
    {
        $this->initLocales();

        $this->originalFields = $fields;

        $this->displayLocalizedNameUsingCallback = self::$displayLocalizedNameByDefaultUsingCallback ?? function (Field $field, string $locale) {
            return ucfirst($field->name)." ({$locale})";
        };

        $this->createTranslatableFields();
    }

    /**
     * Sets the locales array based on the config
     *
     * @throws LocalesNotDefinedException
     */
    protected function initLocales()
    {

        if (count(static::$defaultLocales)) {
            $this->locales = static::$defaultLocales;
            return;
        }

        $localesConfig = (array) config('translatable.locales');
        if (empty($localesConfig)) {
            throw new LocalesNotDefinedException('Please make sure you have run "php artisan config:publish dimsav/laravel-translatable" '.
                ' and that the locales configuration is defined.');
        }

        $locales = [];
        foreach ($localesConfig as $key => $locale) {
            if (is_array($locale)) {
                $locales[] = $key;
                foreach ($locale as $countryLocale) {
                    $locales[] = $key.'-'.$countryLocale;
                }
            } else {
                $locales[] = $locale;
            }
        }

        $this->locales = $locales;
    }

    public static function defaultLocales(array $locales)
    {
        static::$defaultLocales = $locales;
    }

    public function locales(array $locales)
    {
        $this->locales = $locales;

        $this->createTranslatableFields();

        return $this;
    }

    public static function displayLocalizedNameByDefaultUsing(Closure $displayLocalizedNameByDefaultUsingCallback)
    {
        static::$displayLocalizedNameByDefaultUsingCallback = $displayLocalizedNameByDefaultUsingCallback;
    }

    public function displayLocalizedNameUsing(Closure $displayLocalizedNameUsingCallback)
    {
        $this->displayLocalizedNameUsingCallback = $displayLocalizedNameUsingCallback;

        $this->createTranslatableFields();

        return $this;
    }

    protected function createTranslatableFields()
    {
        if ($this->onIndexPage()) {
            $this->data = $this->originalFields;
            return;
        }

        $this->data = [];

        collect($this->locales)
            ->crossJoin($this->originalFields)
            ->eachSpread(function (string $locale, Field $field) {
                $this->data[] = $this->createTranslatedField($field, $locale);
            });
    }

    protected function createTranslatedField(Field $originalField, string $locale): Field
    {
        $translatedField = clone $originalField;

        $originalAttribute = $translatedField->attribute;

        $translatedField->attribute = 'translations';

        $translatedField->name = (count($this->locales) > 1)
            ? ($this->displayLocalizedNameUsingCallback)($translatedField, $locale)
            : $translatedField->name;

        $translatedField
            ->resolveUsing(function ($value, Model $model) use ($translatedField, $locale, $originalAttribute) {
                $translatedField->attribute = 'translations_'.$originalAttribute.'_'.$locale;
                $translatedField->panel = $this->panel;

                return $model->translate($locale) ? $model->translate($locale)->$originalAttribute : null;
            });

        $translatedField->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
            $requestAttributeParts = explode('_', $requestAttribute);
            $locale = array_last($requestAttributeParts);

            array_shift($requestAttributeParts);
            array_pop($requestAttributeParts);

            $key = implode('_', $requestAttributeParts);

            $model->translateOrNew($locale)->$key = $request->get($requestAttribute);
        });

        return $translatedField;
    }

    protected function onIndexPage(): bool
    {
        if (! request()->route()) {
            return false;
        }

        $currentController = str_before(request()->route()->getAction()['controller'], '@');

        return $currentController === ResourceIndexController::class;
    }

}