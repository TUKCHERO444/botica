<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Folder Mapping
    |--------------------------------------------------------------------------
    |
    | Cada módulo del sistema tiene una carpeta designada en Cloudinary para
    | organizar las imágenes y evitar saturación en la carpeta raíz.
    |
    | Para agregar un nuevo módulo, simplemente añade una entrada aquí con
    | el nombre del módulo como clave y el nombre de la carpeta como valor.
    |
    */

    'folders' => [
        'banners'   => 'banners',
        'productos' => 'productos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Folder
    |--------------------------------------------------------------------------
    |
    | Carpeta por defecto para módulos no registrados en el mapa anterior.
    |
    */

    'default_folder' => 'general',
];
