<?php namespace VaneMart;

class Block_File extends BaseBlock {
  /*---------------------------------------------------------------------
  | GET post/index [/TYPE] [/OBJID]
  |
  | Initiates download of a registered file.
  ---------------------------------------------------------------------*/
  function get_dl($id = null) {
    if (ltrim($id, '0..9') === '') {
      $file = File::find($id);
    } else {
      $file = File::where('name', '=', $file)->get();
    }

    if (!$file) {
      return;
    } elseif ($this->can('file.dl.deny.'.$file->id)) {
      return false;
    } else {
      $path = $file->file();

      if (filesize($path) != $file->size) {
        $msg = "Size of local file [$path] is ".filesize($path)." bytes - this".
               " doesn't match the value stored in database ({$file->size} bytes).".
               " The file might have been corrupted or changed directly on disk.";
        Log::error_File($msg);
      }

      return Response::download($file->file(), $file->name, array(
        'Etag'            => $file->md5,
        'Last-Modified'   => gmdate('D, d M Y H:i:s', filemtime($file->file())).' GMT',
        'Content-Type'    => $file->mime ?: 'application/octet-stream',
      ));
    }
  }
}