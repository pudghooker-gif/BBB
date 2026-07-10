<?php

namespace VanguardLTE\B2B\Services;

use InvalidArgumentException;

class B2BApiKeyScopePolicy
{
    const WILDCARD_SCOPE = '*';

    public function defaultScopes()
    {
        return $this->normalize(config('b2b.api_key_default_scopes', []), false);
    }

    public function normalize($scopes, $useDefault = true)
    {
        if ($scopes === null || $scopes === '') {
            return $useDefault ? $this->defaultScopes() : [];
        }

        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $scopes = $decoded;
            } else {
                $scopes = preg_split('/[\s,]+/', $scopes);
            }
        }

        if (!is_array($scopes)) {
            throw new InvalidArgumentException('API key scopes must be a comma-separated string or an array.');
        }

        $normalized = [];
        foreach ($scopes as $scope) {
            $scope = strtolower(trim((string) $scope));
            if ($scope === '') {
                continue;
            }

            if (!preg_match('/\A[a-z0-9.*:_-]+\z/', $scope)) {
                throw new InvalidArgumentException('Invalid API key scope: ' . $scope);
            }

            $normalized[] = $scope;
        }

        return array_values(array_unique($normalized));
    }

    public function allows($storedScopes, array $requiredScopes)
    {
        $requiredScopes = $this->normalize($requiredScopes, false);
        if (count($requiredScopes) === 0) {
            return true;
        }

        $scopes = $this->normalize($storedScopes, false);
        if (in_array(self::WILDCARD_SCOPE, $scopes, true)) {
            return true;
        }

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $scopes, true)) {
                return false;
            }
        }

        return true;
    }
}
