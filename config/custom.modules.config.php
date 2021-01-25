<?php
\Laminas\Loader\AutoloaderFactory::factory(
    array(
        'Laminas\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'RenameUser' => BASE_PATH . '/module/RenameUser/src',
            )
        )
    )
);
return [
    'RenameUser',
];

