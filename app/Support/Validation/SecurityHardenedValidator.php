<?php

namespace VanguardLTE\Support\Validation;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\FilterEmailValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Egulias\EmailValidator\Validation\SpoofCheckValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class SecurityHardenedValidator extends Validator
{
    const MITIGATION_IDS = [
        'GHSA-5vg9-5847-vvmq',
        'CVE-2025-27515',
    ];

    public function validateEmail($attribute, $value, $parameters)
    {
        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return false;
        }

        if (preg_match('/[\r\n]/', (string) $value) > 0) {
            return false;
        }

        $validations = collect($parameters)
            ->unique()
            ->map(function ($validation) {
                if ($validation === 'rfc') {
                    return new RFCValidation;
                } elseif ($validation === 'strict') {
                    return new NoRFCWarningsValidation;
                } elseif ($validation === 'dns') {
                    return new DNSCheckValidation;
                } elseif ($validation === 'spoof') {
                    return new SpoofCheckValidation;
                } elseif ($validation === 'filter') {
                    return new FilterEmailValidation;
                } elseif ($validation === 'filter_unicode') {
                    return FilterEmailValidation::unicode();
                } elseif (is_string($validation) && class_exists($validation)) {
                    return $this->container->make($validation);
                }
            })
            ->values()
            ->all() ?: [new RFCValidation];

        return (new EmailValidator)->isValid($value, new MultipleValidationWithAnd($validations));
    }

    protected function shouldBlockPhpUpload($value, $parameters)
    {
        if (in_array('php', $parameters, true)) {
            return false;
        }

        $phpExtensions = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        ];

        $extension = $value instanceof UploadedFile
            ? $value->getClientOriginalExtension()
            : $value->getExtension();

        return in_array(trim(strtolower($extension)), $phpExtensions, true);
    }
}
