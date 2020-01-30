<?php

namespace Exceedone\Exment\Model;

use Exceedone\Exment\Services\Uuids;
use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Support\Facades\Storage;
use Webpatser\Uuid\Uuid;
use Response;

/**
 * Exment file model.
 * uuid: primary key. it uses as url.
 * path: local file path. it often sets "folder"/"uuid".
 * filename: for download or display name
 */
class File extends ModelBase
{
    use Uuids;
    protected $guarded = ['uuid'];
    // Primary key setting
    protected $primaryKey = 'uuid';
    // increment disable
    public $incrementing = false;

    public function getPathAttribute()
    {
        return path_join($this->local_dirname, $this->local_filename);
    }

    public function getExtensionAttribute()
    {
        if (!isset($this->local_filename)) {
            return null;
        }

        return pathinfo($this->local_filename, PATHINFO_EXTENSION);
    }

    /**
     * save document model
     */
    public function saveDocumentModel($custom_value, $document_name)
    {
        // save Document Model
        $document_model = CustomTable::getEloquent(SystemTableName::DOCUMENT)->getValueModel();
        $document_model->parent_id = $custom_value->id;
        $document_model->parent_type = $custom_value->custom_table->table_name;
        $document_model->setValue([
            'file_uuid' => $this->uuid,
            'document_name' => $document_name,
        ]);
        $document_model->save();

        // execute notify
        $custom_value->notify(false);

        return $document_model;
    }

    public function saveCustomValue($custom_value_id, $custom_column = null, $custom_table = null)
    {
        if (isset($custom_value_id)) {
            $this->parent_id = $custom_value_id;
            $this->parent_type = $custom_table->table_name;
        }
        if (isset($custom_column)) {
            $table_name = $this->local_dirname ?? $custom_table->table_name;
            $custom_column = CustomColumn::getEloquent($custom_column, $table_name);
            $this->custom_column_id = $custom_column->id;
        }
        $this->save();
        return $this;
    }

    /**
     * get the file url
     * @return void
     */
    public static function getUrl($path)
    {
        $file = static::getData($path);
        if (is_null($file)) {
            return null;
        }

        if (!is_nullorempty($file->extension)) {
            $name = "files/".$file->uuid . '.' . $file->extension;
        } else {
            $name = "files/".$file->uuid;
        }
        return admin_url($name);
    }

    /**
     * Save file info to database.
     * *Please call this function before store file.
     * @param string $fileName
     * @return File saved file path
     */
    public static function saveFileInfo(string $dirname, string $filename = null, string $unique_filename = null, $override = false)
    {
        $uuid = make_uuid();

        if (!isset($filename)) {
            list($dirname, $filename) = static::getDirAndFileName($dirname);
        }

        if (!isset($unique_filename)) {
            // get unique name. if different and not override, change name
            $unique_filename = static::getUniqueFileName($dirname, $filename, $override);
        }
        
        // if (!$override && $local_filename != $unique_filename) {
        //     $local_filename = $unique_filename;
        // }

        $file = new self;
        $file->uuid = $uuid;
        $file->local_dirname = $dirname;
        $file->local_filename = $unique_filename;
        $file->filename = $filename;

        $file->save();
        return $file;
    }

    /**
     * delete file info to database
     * @param string|File $file
     * @return void
     */
    public static function deleteFileInfo($file)
    {
        if (is_string($file)) {
            $file = static::getData($file);
        }

        if (is_null($file)) {
            return;
        }
        $path = $file->path;
        $file->delete();

        Storage::disk(config('admin.upload.disk'))->delete($path);
    }

    /**
     * Download file
     */
    public static function downloadFile($uuid)
    {
        $uuid = pathinfo($uuid, PATHINFO_FILENAME);
        $data = static::getData($uuid);
        if (!$data) {
            abort(404);
        }
        $path = $data->path;
        $exists = Storage::disk(config('admin.upload.disk'))->exists($path);
        
        if (!$exists) {
            abort(404);
        }

        // if has parent_id, check permission
        if (isset($data->parent_id) && isset($data->parent_type)) {
            $custom_table = CustomTable::getEloquent($data->parent_type);
            if (!$custom_table->hasPermissionData($data->parent_id)) {
                abort(403);
            }
        }

        $file = Storage::disk(config('admin.upload.disk'))->get($path);
        $type = Storage::disk(config('admin.upload.disk'))->mimeType($path);
        // get page name
        $name = rawurlencode($data->filename);
        // create response
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
        $response->header('Content-Disposition', "inline; filename*=UTF-8''$name");

        return $response;
    }

    /**
     * Download favicon image
     */
    public static function downloadFavicon()
    {
        $record = System::where('system_name', 'site_favicon')->first();

        if (!isset($record)) {
            abort(404);
        }

        return self::downloadFile($record->system_value);
    }

    /**
     * Delete file and document info
     */
    public static function deleteFile($uuid, $options = [])
    {
        $options = array_merge(
            [
                'removeDocumentInfo' => true,
                'removeFileInfo' => true,
            ],
            $options
        );
        $data = static::getData($uuid);
        if (!$data) {
            abort(404);
        }

        // if not has delete setting, abort 403
        if (boolval(config('exment.file_delete_useronly', false)) && $data->created_user_id != \Exment::user()->base_user_id) {
            abort(403);
        }

        $path = $data->path;
        $exists = Storage::disk(config('admin.upload.disk'))->exists($path);
        
        // if exists, delete file
        if ($exists) {
            Storage::disk(config('admin.upload.disk'))->delete($path);
        }

        // if has document, remove document info
        if (boolval($options['removeDocumentInfo'])) {
            $column_name = CustomTable::getEloquent(SystemTableName::DOCUMENT)->getIndexColumnName('file_uuid');
        
            // delete document info
            getModelName(SystemTableName::DOCUMENT)
                ::where($column_name, $uuid)
                ->delete();
        }
        
        // delete file info
        if (boolval($options['removeFileInfo'])) {
            $file = static::getData($uuid);
            static::deleteFileInfo($file);
        }

        return response([
            'status'  => true,
            'message' => trans('admin.delete_succeeded'),
        ]);
    }

    /**
     * get file object(laravel)
     */
    public static function getFile($uuid, Closure $authCallback = null)
    {
        $data = static::getData($uuid);
        if (!$data) {
            return null;
        }
        $path = $data->path;
        $exists = Storage::disk(config('admin.upload.disk'))->exists($path);
        
        if (!$exists) {
            return null;
        }
        if ($authCallback) {
            $authCallback($data);
        }

        return Storage::disk(config('admin.upload.disk'))->get($path);
    }

    /**
     * get CustomValue from form. for saved CustomValue
     */
    public function getCustomValueFromForm($custom_value, $uuidObj)
    {
        // replace $uuidObj[path] for windows
        $path = str_replace('\\', '/', $this->path);

        // get from model
        $value = $custom_value->toArray();
        // if match path, return this model's id
        if (array_get($value, 'value.' . array_get($uuidObj, 'column_name')) == $path) {
            return $value;
        }

        return null;
    }

    /**
     * Save file table on db and store the uploaded file on a filesystem disk.
     *
     * @param  string  $disk disk name
     * @param  string  $path directory path
     * @return string|false
     */
    public static function put($path, $content, $override = false)
    {
        $file = static::saveFileInfo($path, null, null, $override);
        Storage::disk(config('admin.upload.disk'))->put($file->path, $content);
        return $file;
    }
    
    /**
     * Save file table on db and store the uploaded file on a filesystem disk.
     *
     * @param  string  $content file content
     * @param  string  $disk disk name
     * @param  string  $path directory path
     * @return string|false
     */
    public static function store($content, $dirname)
    {
        $file = static::saveFileInfo($dirname, null, null, false);
        $content->store($file->local_dirname, config('admin.upload.disk'));
        return $file;
    }
    
    /**
     * Save file table on db and store the uploaded file on a filesystem disk.
     *
     * @param  string  $content file content
     * @param  string  $dirname directory path
     * @param  string  $name file name. the name is shown by display
     * @param  string  $local_filename local file name.
     * @param  bool  $override if file already exists, override
     * @return string|false
     */
    public static function storeAs($content, $dirname, $name, $override = false)
    {
        $file = static::saveFileInfo($dirname, $name, null, $override);
        if (is_string($content)) {
            \Storage::disk(config('admin.upload.disk'))->put(path_join($dirname, $file->local_filename), $content);
        } else {
            $content->storeAs($dirname, $file->local_filename, config('admin.upload.disk'));
        }
        return $file;
    }

    /**
     * Get file model using path or uuid
     */
    protected static function getData($pathOrUuid)
    {
        if (is_nullorempty($pathOrUuid)) {
            return null;
        }

        // get by uuid
        $file = static::where('uuid', $pathOrUuid)->first();

        if (is_null($file)) {
            // get by $dirname, $filename
            list($dirname, $filename) = static::getDirAndFileName($pathOrUuid);
            $file = static::where('local_dirname', $dirname)
                ->where('local_filename', $filename)
                ->first();
            if (is_null($file)) {
                return null;
            }
        }
        return $file;
    }
    
    /**
     * get unique file name
     */
    public static function getUniqueFileName($dirname, $filename = null, $override = false)
    {
        if ($override) {
            if (!isset($filename)) {
                list($dirname, $filename) = static::getDirAndFileName($dirname);
            }
    
            // get by dir and filename
            $file = static::where('local_dirname', $dirname)->where('filename', $filename)->first();
    
            if (!is_null($file)) {
                return $file->local_filename;
            }
        }

        $ext = file_ext($filename);
        return make_uuid() . (!is_nullorempty($ext) ? '.'.$ext : '');

        // // create file name.
        // // get ymdhis string
        // $path = url_join($dirname, $filename);

        // // check file exists
        // // if exists, use uuid
        // if (\File::exists(getFullpath($path, config('admin.upload.disk')))) {
        //     $ext = file_ext($filename);
        //     return make_uuid() . (!is_nullorempty($ext) ? '.'.$ext : '');
        // }
        // return $filename;
    }

    /**
     * get directory and filename from path
     */
    public static function getDirAndFileName($path)
    {
        $dirname = dirname($path);
        $filename = mb_basename($path);
        return [$dirname, $filename];
    }
}
