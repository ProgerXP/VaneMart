<?php namespace VaneMart;

class Block_File extends BaseBlock {
  /*---------------------------------------------------------------------
  | GET file/index /REF
  |
  | Initiates download of a registered file.
  -----------------------------------------------------------------------
  | * REF           - REQUIRED; either contain full name (e.g.
  |   /file/a%20name.txt) or numeric ID optionally with extension
  |   (e.g. /file/123.dat) for better visual link perception.
  ---------------------------------------------------------------------*/
  function get_dl($id = null) {
    $digitless = ltrim($id, '0..9');

    // file link can be either
    if ($digitless === '' or $digitless[0] === '.') {
      $file = File::find(strtok($id, '.'));
    } else {
      $file = File::where('name', '=', $id)->get();
    }

    if (!$file) {
      return;
    } elseif ($this->can('file.dl.deny.'.$file->id)) {
      return false;
    } else {
      $path = $file->file();
      $override = Event::until('file.dl.before', array(&$path, &$file, $this));

      if ($override !== null) {
        return $override;
      } elseif (! $file instanceof File) {
        return E_SERVER;
      } else {
        // In case the model was changed during event firing.
        $file->save();
        return Event::until('file.dl.response', array(&$path, $file, $this));
      }
    }
  }
}