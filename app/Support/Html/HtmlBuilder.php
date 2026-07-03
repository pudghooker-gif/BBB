<?php

namespace VanguardLTE\Support\Html;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

class HtmlBuilder
{
    protected $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    public function script($url, $attributes = [], $secure = null)
    {
        $attributes['src'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<script' . $this->attributes($attributes) . '></script>');
    }

    public function style($url, $attributes = [], $secure = null)
    {
        $attributes = array_merge([
            'media' => 'all',
            'type' => 'text/css',
            'rel' => 'stylesheet',
        ], $attributes);
        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    public function attributes(array $attributes)
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
            }

            if ($value === false || is_null($value)) {
                continue;
            }

            if ($value === true) {
                $html[] = $this->entities($key);
                continue;
            }

            $html[] = $this->entities($key) . '="' . $this->entities($value) . '"';
        }

        return count($html) ? ' ' . implode(' ', $html) : '';
    }

    public function entities($value)
    {
        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8', false);
    }

    public function toHtmlString($html)
    {
        return new HtmlString($html);
    }
}
