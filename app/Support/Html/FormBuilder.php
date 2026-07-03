<?php

namespace VanguardLTE\Support\Html;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class FormBuilder
{
    protected $html;
    protected $url;
    protected $session;
    protected $reserved = ['method', 'url', 'route', 'action', 'files'];
    protected $spoofedMethods = ['DELETE', 'PATCH', 'PUT'];

    public function __construct(HtmlBuilder $html, UrlGenerator $url, Session $session)
    {
        $this->html = $html;
        $this->url = $url;
        $this->session = $session;
    }

    public function open(array $options = [])
    {
        $method = Arr::get($options, 'method', 'post');
        $attributes = [
            'method' => $this->formMethod($method),
            'action' => $this->action($options),
            'accept-charset' => 'UTF-8',
        ];

        if (!empty($options['files'])) {
            $options['enctype'] = 'multipart/form-data';
        }

        $attributes = array_merge($attributes, Arr::except($options, $this->reserved));

        return $this->toHtmlString('<form' . $this->html->attributes($attributes) . '>' . $this->appendage($method));
    }

    public function close()
    {
        return $this->toHtmlString('</form>');
    }

    public function token()
    {
        return $this->hidden('_token', $this->session->token());
    }

    public function hidden($name, $value = null, $attributes = [])
    {
        return $this->input('hidden', $name, $value, $attributes);
    }

    public function checkbox($name, $value = 1, $checked = null, $attributes = [])
    {
        if ($checked) {
            $attributes['checked'] = true;
        }

        return $this->input('checkbox', $name, $value, $attributes);
    }

    public function select($name, $list = [], $selected = null, array $attributes = [], array $optionsAttributes = [], array $optgroupsAttributes = [])
    {
        if (!isset($attributes['name'])) {
            $attributes['name'] = $name;
        }

        $html = [];
        if (isset($attributes['placeholder'])) {
            $html[] = $this->option($attributes['placeholder'], '', $selected);
            unset($attributes['placeholder']);
        }

        foreach ($this->iterableToArray($list) as $value => $display) {
            $optionAttributes = isset($optionsAttributes[$value]) ? $optionsAttributes[$value] : [];
            $optgroupAttributes = isset($optgroupsAttributes[$value]) ? $optgroupsAttributes[$value] : [];

            if (is_array($display) || $display instanceof \Traversable) {
                $html[] = $this->optionGroup($display, $value, $selected, $optgroupAttributes, $optionsAttributes);
            } else {
                $html[] = $this->option($display, $value, $selected, $optionAttributes);
            }
        }

        return $this->toHtmlString('<select' . $this->html->attributes($attributes) . '>' . implode('', $html) . '</select>');
    }

    protected function input($type, $name, $value = null, array $attributes = [])
    {
        $attributes = array_merge([
            'type' => $type,
            'name' => $name,
            'value' => $value,
        ], $attributes);

        return $this->toHtmlString('<input' . $this->html->attributes($attributes) . '>');
    }

    protected function optionGroup($list, $label, $selected, array $attributes = [], array $optionsAttributes = [])
    {
        $attributes['label'] = $label;
        $html = [];

        foreach ($this->iterableToArray($list) as $value => $display) {
            $optionAttributes = isset($optionsAttributes[$value]) ? $optionsAttributes[$value] : [];
            $html[] = $this->option($display, $value, $selected, $optionAttributes);
        }

        return '<optgroup' . $this->html->attributes($attributes) . '>' . implode('', $html) . '</optgroup>';
    }

    protected function option($display, $value, $selected, array $attributes = [])
    {
        $attributes['value'] = $value;
        if ($this->isSelected($value, $selected)) {
            $attributes['selected'] = true;
        }

        return '<option' . $this->html->attributes($attributes) . '>' . $this->html->entities($display) . '</option>';
    }

    protected function isSelected($value, $selected)
    {
        if ($selected instanceof \Traversable) {
            $selected = iterator_to_array($selected);
        }

        if (is_array($selected)) {
            foreach ($selected as $candidate) {
                if ((string) $candidate === (string) $value) {
                    return true;
                }
            }

            return false;
        }

        return !is_null($selected) && (string) $selected === (string) $value;
    }

    protected function appendage($method)
    {
        $method = strtoupper($method);
        $appendage = '';

        if (in_array($method, $this->spoofedMethods, true)) {
            $appendage .= $this->hidden('_method', $method);
        }

        if ($method !== 'GET') {
            $appendage .= $this->token();
        }

        return $appendage;
    }

    protected function formMethod($method)
    {
        return strtoupper($method) === 'GET' ? 'GET' : 'POST';
    }

    protected function action(array $options)
    {
        if (isset($options['url'])) {
            return $this->urlAction($options['url']);
        }

        if (isset($options['route'])) {
            return $this->routeAction($options['route']);
        }

        if (isset($options['action'])) {
            return $this->controllerAction($options['action']);
        }

        return $this->url->current();
    }

    protected function urlAction($options)
    {
        if (is_array($options)) {
            return $this->url->to($options[0], array_slice($options, 1));
        }

        return $this->url->to($options);
    }

    protected function routeAction($options)
    {
        if (is_array($options)) {
            return $this->url->route($options[0], array_slice($options, 1));
        }

        return $this->url->route($options);
    }

    protected function controllerAction($options)
    {
        if (is_array($options)) {
            return $this->url->action($options[0], array_slice($options, 1));
        }

        return $this->url->action($options);
    }

    protected function iterableToArray($value)
    {
        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return (array) $value;
    }

    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }
}
