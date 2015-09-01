<?php
namespace Joomlatools\Console\Command\Extension;

class RegisterDefaults
{
    public $typeMap = array(
        'com_' => 'component',
        'mod_' => 'module',
        'plg_' => 'plugin',
        'pkg_' => 'package',
        'lib_' => 'library',
        'tpl_' => 'template',
        'lng_' => 'language'
    );

    public $exceptions = array(
        'module' => array(
            'require' => array(
                'model' => '/administrator/components/com_modules/models/module.php'
            ),
            'model' => '\\ModulesModelModule',
            'table' => array(
                'type' => 'module',
                'prefix' => 'JTable'
            ),
        ),
        'template' => array(
            'require' => array(
                'model' => '/administrator/components/com_templates/models/style.php',
                'table' => '/administrator/components/com_templates/tables/style.php'
            ),
            'model' => 'TemplatesModelStyle',
            'table' => array(
                'type' => 'Style',
                'prefix' => 'TemplatesTable'
            ),
        ));

    public function __construct(){
        return $this;
    }
}