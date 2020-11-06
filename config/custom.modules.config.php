<?php
\Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'RenameUser' => BASE_PATH . '/module/RenameUser/src',
            )
        )
    )
);
return [
    'RenameUser',
];
