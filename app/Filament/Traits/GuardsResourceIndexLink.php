<?php

namespace App\Filament\Traits;

/**
 * For resource pages whose resource uses HasViewIndexPermission: drops the
 * breadcrumb link to the list page for users without the view_index
 * permission, so it does not lead to a 403.
 */
trait GuardsResourceIndexLink
{
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $resource = static::getResource();

        if ($resource::canViewIndex()) {
            return $breadcrumbs;
        }

        $indexUrl = $resource::getUrl('index');

        if (! array_key_exists($indexUrl, $breadcrumbs)) {
            return $breadcrumbs;
        }

        $result = [];

        foreach ($breadcrumbs as $url => $label) {
            if ($url === $indexUrl || is_int($url)) {
                $result[] = $label;

                continue;
            }

            $result[$url] = $label;
        }

        return $result;
    }
}
