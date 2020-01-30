<?php
namespace Exceedone\Exment\Services\TemplateImportExport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomColumnMulti;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomForm;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\CustomCopy;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\Dashboard;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\Storage\Disk\TemplateDiskService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

/**
 * Import Template
 */
class TemplateImporter
{
    protected $diskService;

    public function __construct()
    {
        $this->diskService = new TemplateDiskService();
    }

    /**
     * get template list (get from app folder and vendor/exceedone/exment/templates)
     */
    public function getTemplates()
    {
        return array_merge($this->getUserTemplates(), $this->getLocalTemplates());
    }
    
    /**
     * Import template (from display. select item)
     */
    public function importTemplate($importKeys)
    {
        try {
            if (!is_array($importKeys)) {
                $importKeys = [$importKeys];
            }
            
            $items = $this->getTemplates();
            foreach (array_filter($importKeys) as $importKey) {
                $item = collect($items)->first(function ($item) use ($importKey) {
                    $importItem = json_decode($importKey, true);
                    return array_get($item, 'template_type') == array_get($importItem, 'template_type')
                        && array_get($item, 'template_name') == array_get($importItem, 'template_name');
                });
                if (!isset($item)) {
                    continue;
                }
                $templateName = array_get($item, 'template_name');

                $this->diskService->initDiskService($templateName);

                // if local item
                if (array_get($item, 'template_type') == 'local') {
                    $templates_path = $this->getTemplatePath();
                    if (!isset($templateName)) {
                        continue;
                    }

                    $path = "$templates_path/$templateName/config.json";
                            
                    // If file not exists
                    if (!File::exists($path)) {
                        // TODO:Error
                    }
                    $this->importFromFile(File::get($path), [
                        'basePath' => "$templates_path/$templateName",
                    ]);
                }

                // if crowd
                if (array_get($item, 'template_type') == 'user') {
                    $this->diskService->syncFromDisk();
                    $path = path_join($this->diskService->localSyncDiskItem()->dirFullPath(), $templateName, 'config.json');
    
                    if (!File::exists($path)) {
                        continue;
                    }
                            
                    $this->importFromFile(File::get($path), [
                        'basePath' => path_join($this->diskService->localSyncDiskItem()->dirFullPath(), $templateName),
                    ]);
                }
            }
        } finally {
            if (isset($this->diskService)) {
                $this->diskService->deleteTmpDirectory();
            }
        }
    }
 
    /**
     * Delete template (from display. select item)
     */
    public function deleteTemplate($teplate_name)
    {
        $diskItem = $this->diskService->diskItem();
        $disk = $diskItem->disk();

        if (!$disk->exists($teplate_name)) {
            return;
        }

        $disk->deleteDirectory($teplate_name);
    }

    
    /**
     * get user uploaded template list (get from storage folder)
     */
    protected function getUserTemplates()
    {
        $templates = [];

        $diskItem = $this->diskService->diskItem();
        $disk = $diskItem->disk();

        $directories = $disk->directories();
        foreach ($directories as $templates_path) {
            $path = path_join($templates_path, "config.json");
            if (!$disk->exists($path)) {
                continue;
            }

            $locale = \App::getLocale();
            try {
                $dirname = pathinfo($path)['dirname'];
                $json = json_decode($disk->get($path), true);
                // merge language file
                $langpath = "$dirname/lang/$locale/lang.json";
                if ($disk->exists($langpath)) {
                    $lang = json_decode($disk->get($langpath), true);
                    $json = $this->mergeTemplate($json, $lang);
                }
                // add thumbnail
                if (isset($json['thumbnail'])) {
                    $thumbnail_path = path_join($dirname, $json['thumbnail']);
                    if ($disk->exists($thumbnail_path)) {
                        // if local, get path
                        if ($diskItem->isDriverLocal()) {
                            $json['thumbnail_file'] = base64_encode(file_get_contents(path_join($diskItem->dirFullPath(), $thumbnail_path)));
                        }
                        // if crowd, get url
                        else {
                            $json['thumbnail_file'] = base64_encode($disk->get($thumbnail_path));
                        }
                    }
                }

                $json['template_type'] = 'user';
                $templates[] = $json;
            } catch (Exception $exception) {
                //TODO:error handling
            }
        }

        return $templates;
    }

    /**
     * get local template list (get from storage folder)
     */
    protected function getLocalTemplates()
    {
        $templates = [];

        $templates_path = $this->getTemplatePath();
        $paths = File::glob("$templates_path/*/config.json");
        $locale = \App::getLocale();
        foreach ($paths as $path) {
            try {
                $dirname = pathinfo($path)['dirname'];
                $json = json_decode(File::get($path), true);
                // merge language file
                $langpath = "$dirname/lang/$locale/lang.json";
                if (File::exists($langpath)) {
                    $lang = json_decode(File::get($langpath), true);
                    $json = $this->mergeTemplate($json, $lang);
                }
                // add thumbnail
                if (isset($json['thumbnail'])) {
                    $thumbnail_fullpath = path_join($dirname, $json['thumbnail']);
                    if (File::exists($thumbnail_fullpath)) {
                        $json['thumbnail_file'] = base64_encode(file_get_contents($thumbnail_fullpath));
                    }
                }
                
                $json['template_type'] = 'local';
                $templates[] = $json;
            } catch (Exception $exception) {
                //TODO:error handling
            }
        }

        return $templates;
    }


    /**
     * Import System template (from command)
     */
    public function importSystemTemplate($is_update = false)
    {
        // get vendor folder
        $templates_base_path = exment_package_path('system_template');
        $path = "$templates_base_path/config.json";

        // If file not exists
        if (!File::exists($path)) {
            // TODO:Error
        }

        $this->importFromFile(File::get($path), [
            'system_flg' => true,
            'is_update' => $is_update,
            'basePath' => $templates_base_path,
        ]);
    }

    /**
     * Upload template and import (from display)
     */
    public function uploadTemplate($uploadFile)
    {
        try {
            $tmpDiskItem = $this->diskService->tmpDiskItem();
            $tmpDisk = $tmpDiskItem->disk();

            // store uploaded file
            $tmpfolderpath = $tmpDiskItem->dirFullPath();
            $filename = $tmpDisk->put($tmpDiskItem->dirName(), $uploadFile);
            $fullpath = $tmpDisk->path($filename);

            // zip
            $zip = new ZipArchive;
            $res = $zip->open($fullpath);
            if ($res !== true) {
                //TODO:error
            }

            //Check existed file config (config.json)
            $config_path = null;
            $thumbnail_path = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $fileInfo = $zip->getNameIndex($i);
                if ($fileInfo === 'config.json') {
                    $zip->extractTo($tmpfolderpath);
                    $config_path = array_get($stat, 'name');
                } elseif (pathinfo($fileInfo)['filename'] === 'thumbnail') {
                    $thumbnail_path = array_get($stat, 'name');
                }
            }
            $zip->close();
            
            //
            if (isset($config_path)) {
                // get config.json
                $json = json_decode(File::get(path_join($tmpfolderpath, $config_path)), true);
                if (!isset($json)) {
                    // TODO:Error
                    return;
                }

                // get template name
                $template_name = array_get($json, 'template_name');
                if (!isset($template_name)) {
                    // TODO:Error
                    return;
                }

                // copy to app/templates path
                $files = [
                    path_join($tmpDiskItem->dirName(), $config_path) => path_join($template_name, 'config.json')
                ];
                if (isset($thumbnail_path)) {
                    $files[path_join($tmpDiskItem->dirName(), $thumbnail_path)] = path_join($template_name, pathinfo(path_join($tmpfolderpath, $thumbnail_path))['basename']);
                }
                $this->diskService->upload($files);

                $this->importFromFile(File::get(path_join($tmpfolderpath, $config_path)), [
                    'basePath' => $tmpfolderpath,
                ]);
            }
        } catch (\Exception $ex) {
            throw $ex;
        } finally {
            // delete zip
            if (isset($tmpfolderpath)) {
                File::deleteDirectory($tmpfolderpath);
            }

            if (isset($fullpath)) {
                File::delete($fullpath);
                //unlink($fullpath);
            }
            
            $this->diskService->deleteTmpDirectory();
        }
    }

    /**
     * upload from excel and import
     */
    public function uploadTemplateExcel($file)
    {
        // template file settings as json
        $settings = [];

        // loop for excel sheets
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($file->getRealPath());
        foreach (Define::TEMPLATE_IMPORT_EXCEL_SHEETNAME as $sheetname) {
            $sheet = $spreadsheet->getSheetByName($sheetname);

            // if not exists sheet, set empty array
            if (!isset($sheet)) {
                $settings[$sheetname] = [];
                continue;
            }

            $data = getDataFromSheet($sheet, 2, true, true);
            // set config json
            $settings[$sheetname] = $data;
        }

        if (array_key_value_exists('custom_tables', $settings)) {
            foreach ($settings['custom_tables'] as &$custom_table) {
                $custom_table = array_dot_reverse($custom_table);

                $form_action_disable_flg = array_get($custom_table, 'options.form_action_disable_flg');
                if(!is_nullorempty($form_action_disable_flg)){
                    array_set($custom_table, 'options.form_action_disable_flg', stringToArray($form_action_disable_flg));
                }
            }
        }

        // convert custom_columns to custom_tables->custom_columns
        if (array_key_exists('custom_columns', $settings) && array_key_exists('custom_tables', $settings)) {
            $custom_columns = array_get($settings, 'custom_columns');
            foreach ($custom_columns as &$custom_column) {
                // get table name
                $table_name = array_get($custom_column, 'table_name');
                // find $settings->custom_tables
                if (!isset($table_name)) {
                    continue;
                }
                // get target custom table
                foreach ($settings['custom_tables'] as &$custom_table) {
                    if ($table_name != array_get($custom_table, 'table_name')) {
                        continue;
                    }
                    // set custom_column to $custom_table
                    $target_custom_table_columns = array_get($custom_table, 'custom_columns', []);
                    // remove custom column table name
                    array_forget($custom_column, 'table_name');
                    $target_custom_table_column = array_dot_reverse($custom_column);
                    $target_custom_table_columns[] = array_dot_reverse($custom_column);
                    $custom_table['custom_columns'] = $target_custom_table_columns;
                    // jump to next column
                    break;
                }
            }
        }
        // forget custom_columns array
        array_forget($settings, 'custom_columns');

        // convert custom_column_multisettings to custom_tables->custom_column_multisettings
        if (array_key_exists('custom_column_multisettings', $settings) && array_key_exists('custom_tables', $settings)) {
            $custom_column_multisettings = array_get($settings, 'custom_column_multisettings');
            foreach ($custom_column_multisettings as &$custom_column_multisetting) {
                // get table name
                $table_name = array_get($custom_column_multisetting, 'table_name');
                // find $settings->custom_tables
                if (!isset($table_name)) {
                    continue;
                }
                // get target custom table
                foreach ($settings['custom_tables'] as &$custom_table) {
                    if ($table_name != array_get($custom_table, 'table_name')) {
                        continue;
                    }
                    // set table_label_column_name
                    $priority = 1;
                    foreach(range(1, 5) as $index){
                        $table_label_column_name = array_get($custom_column_multisetting, "table_label_column_name_$index");
                        if(\is_nullorempty($table_label_column_name)){
                            continue;
                        }

                        $custom_table['custom_column_multisettings'][] = [
                            'priority' => $priority++,
                            'multisetting_type' => 2,
                            'options' => [
                                'table_label_table_name' => $table_name,
                                'table_label_column_name' => $table_label_column_name,
                            ]
                        ];
                    }
                }
            }
        }
        // forget custom_column_multisettings array
        array_forget($settings, 'custom_column_multisettings');

        return $settings;
    }

    /**
     * execute import from file
     */
    protected function importFromFile($jsonString, $options = [])
    {
        extract(array_merge(
            [
                'system_flg' => false,
                'is_update' => false,
                'basePath' => null,
            ],
            $options
        ));

        $json = json_decode($jsonString, true);
        if (!isset($json)) {
            // TODO:Error
            return;
        }

        // merge language file
        $locale = \App::getLocale();
        $langpath = "$basePath/lang/$locale/lang.json";

        if (File::exists($langpath)) {
            $lang = json_decode(File::get($langpath), true);
            $json = $this->mergeTemplate($json, $lang);
        }

        $this->import($json, $system_flg, $is_update);

        if (!$is_update) {
            // get lang datafile
            $dataPath = path_join($basePath, 'data', $locale);

            if (File::exists($dataPath)) {
                $this->importData($dataPath);
            } else {
                $dataPath = path_join($basePath, 'data');
                // if exists, execute data copy
                if (File::exists($dataPath)) {
                    $this->importData($dataPath);
                }
            }
        }
    }

    /**
     * import data using csv, xlsx
     */
    public function importData($dataPath)
    {
        $files = File::files($dataPath);

        // get all csv files
        $files = collect($files)->filter(function ($value) {
            return in_array(pathinfo($value)['extension'], ['csv', 'xlsx']);
        });
        
        // loop csv file
        foreach ($files as $file) {
            $table_name = file_ext_strip(pathinfo($file)['basename']);
            $format = file_ext(pathinfo($file)['basename']);

            $custom_table = CustomTable::getEloquent($table_name);
            if (!isset($custom_table)) {
                continue;
            }

            // execute import
            $service = (new DataImportExport\DataImportExportService())
                ->importAction(new DataImportExport\Actions\Import\CustomTableAction(
                    [
                        'custom_table' => $custom_table,
                    ]
                ))
                ->format($format);
            $service->import($file->getRealPath());
        }
    }

    /**
     * execute
     */
    public function import($json, $system_flg = false, $is_update=false)
    {
        System::clearCache();
        
        DB::transaction(function () use ($json, $system_flg, $is_update) {
            // Loop by tables
            foreach (array_get($json, "custom_tables", []) as $table) {
                // Create tables. --------------------------------------------------
                $obj_table = CustomTable::importTemplate($table, $is_update, [
                    'system_flg' => $system_flg
                ]);
            }

            // Re-Loop by tables and create columns
            foreach (array_get($json, "custom_tables", []) as $table) {
                // find tables. --------------------------------------------------
                $obj_table = CustomTable::firstOrNew(['table_name' => array_get($table, 'table_name')]);
                // Create columns. --------------------------------------------------
                foreach (array_get($table, 'custom_columns', []) as $column) {
                    //
                    array_forget($column, 'options.select_target_view_suuid');
                    CustomColumn::importTemplate($column, $is_update, [
                        'system_flg' => $system_flg,
                        'parent' => $obj_table,
                    ]);
                }
                
                // Create columnmultis. --------------------------------------------------
                foreach (array_get($table, 'custom_column_multisettings', []) as $column) {
                    CustomColumnMulti::importTemplate($column, $is_update, [
                        'parent' => $obj_table,
                    ]);
                }
            }

            // re-loop columns. because we have to get other column id --------------------------------------------------
            foreach (array_get($json, "custom_tables", []) as $table) {
                // find tables. --------------------------------------------------
                $obj_table = CustomTable::firstOrNew(['table_name' => array_get($table, 'table_name')]);
                // get columns. --------------------------------------------------
                if (array_key_exists('custom_columns', $table)) {
                    foreach (array_get($table, 'custom_columns') as $column) {
                        CustomColumn::importTemplateRelationColumn($column, $is_update, [
                            'system_flg' => $system_flg,
                            'parent' => $obj_table,
                        ]);
                    }
                }
            }

            // Loop relations.
            foreach (array_get($json, "custom_relations", []) as $relation) {
                CustomRelation::importTemplate($relation, $is_update);
            }

            // loop for form
            foreach (array_get($json, "custom_forms", []) as $form) {
                CustomForm::importTemplate($form, $is_update);
            }

            // loop for view
            foreach (array_get($json, "custom_views", []) as $view) {
                CustomView::importTemplate($view, $is_update);
            }

            // re-loop columns. because we have to get other column views --------------------------------------------------
            foreach (array_get($json, "custom_tables", []) as $table) {
                // find tables. --------------------------------------------------
                $obj_table = CustomTable::firstOrNew(['table_name' => array_get($table, 'table_name')]);
                // get columns. --------------------------------------------------
                if (array_key_exists('custom_columns', $table)) {
                    foreach (array_get($table, 'custom_columns') as $column) {
                        CustomColumn::importTemplateTargetView($column, $is_update, [
                            'system_flg' => $system_flg,
                            'parent' => $obj_table,
                        ]);
                    }
                }
            }

            // loop for copy
            foreach (array_get($json, "custom_copies", []) as $copy) {
                CustomCopy::importTemplate($copy, $is_update);
            }

            // Loop for role groups.
            foreach (array_get($json, "role_groups", []) as $role) {
                // Create role. --------------------------------------------------
                RoleGroup::importTemplate($role, $is_update);
            }

            // loop for dashboard
            foreach (array_get($json, "dashboards", []) as $dashboard) {
                // Create dashboard --------------------------------------------------
                Dashboard::importTemplate($dashboard, $is_update);
            }

            // loop for menu
            if (array_key_exists('admin_menu', $json)) {
                // order by parent_name is null, not null
                $menulist = collect(array_get($json, "admin_menu"));
                // loop for parent_name is null(root), and next parent name has value
                foreach ([0, 1] as $hasname) {
                    foreach ($menulist as $menu) {
                        // Create menu. --------------------------------------------------
                        Menu::importTemplate($menu, $is_update, [
                            'hasname' => $hasname,
                        ]);
                    }
                }
            }
        });

        // after transaction, execute create table etc
        foreach (array_get($json, "custom_tables", []) as $table) {
            $obj = CustomTable::getEloquent(array_get($table, 'table_name'))->importSaved();

            if (array_key_exists('custom_columns', $table)) {
                foreach (array_get($table, 'custom_columns') as $column) {
                    CustomColumn::getEloquent(array_get($column, 'column_name'), $obj)->importSaved();
                }
            }
        }

        // patch use_label_flg
        \Artisan::call('exment:patchdata', ['action' => 'use_label_flg']);

        System::clearCache();
    }

    protected function getTemplatePath()
    {
        return exment_package_path('templates');
    }

    /**
     * create model path from table name.
     */
    protected function getModelPath($tablename)
    {
        if (is_string($tablename)) {
            $classname = Str::studly(Str::singular($tablename));
            $fillpath = namespace_join("Exceedone", "Exment", "Model", $classname);
            if (class_exists($fillpath)) {
                return $fillpath;
            }
        }
        return null;
    }
    /**
     * update template json by language json.
     */
    protected function mergeTemplate($json, $langJson, $fillpath = null)
    {
        $result = [];

        foreach ($json as $key => $val) {
            $langdata = null;
            if (isset($fillpath)) {
                $langdata = $fillpath::searchLangData($val, $langJson);
            } elseif (isset($langJson[$key])) {
                $langdata = $langJson[$key];
            }

            // substitute the key which is only in template.
            if (!isset($langdata)) {
                $result[$key] = $val;
                continue;
            }

            if (is_array($json[$key]) && is_array($langdata)) {
                // if values are both array, call this method recursion
                $result[$key] = $this->mergeTemplate($json[$key], $langdata, $this->getModelPath($key));

                // if this model contains table and contains children, get the classname and call child value
                if (isset($fillpath) && property_exists($fillpath, 'templateItems') && array_has($fillpath::$templateItems, 'children')) {
                    $children = array_get($fillpath::$templateItems, 'children');
                    foreach ($children as $childkey => $childpath) {
                        if (!array_has($json[$key], $childkey)) {
                            continue;
                        }
                        if (!isset($langJson[$key]) || !array_has($langJson[$key], $childkey)) {
                            continue;
                        }
                        // call mergeTemplate for child
                        $result[$key][$childkey] = $this->mergeTemplate($json[$key][$childkey], $langJson[$key][$childkey], $childpath);
                    }
                }
            } else {
                // replace if values are both strings
                $result[$key] = $langdata;
            }
        }
        return $result;
    }
}
