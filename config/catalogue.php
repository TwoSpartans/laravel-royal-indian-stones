<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Existing HTML site
    |--------------------------------------------------------------------------
    |
    | Catalogue and content imports read product pages from this directory.
    | The default is a sibling folder named Royal-Indian-Stones.
    |
    */

    'source' => env('CATALOGUE_SOURCE', dirname(base_path()).DIRECTORY_SEPARATOR.'Royal-Indian-Stones'),

];
