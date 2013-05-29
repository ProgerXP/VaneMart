<?php namespace VaneMart;

class Block_Post extends BaseBlock {
  protected function init() {
    $this->filter('before', 'csrf')->only('add')->on('post');
    $this->filter('before', 'vane::auth:!post.add.deny')->only('add');
  }

  function accessible($action, $object, $parent = 1) {
    return ($object or $this->can("post.$action.objless")) and
           ($parent or !$this->can("post.$action.noroot"));
  }

  function userCanAttach() {
    return !$this->can('post.attach.deny');
  }

  function uploadedFileNames() {
    $result = array();
    $files = S(Input::file('attach'), '(array) ?');

    foreach ($files['error'] as $i => $error) {
      $error or $result[] = $files['name'][$i];
    }

    return $result;
  }

  /*---------------------------------------------------------------------
  | GET post/index [/TYPE] [/OBJID]
  |
  | List posts belonging to current object.
  |----------------------------------------------------------------------
  | * TYPE          - REQUIRED; might be optional depending on permissions.
  |   This is type of object posts belong to: blog, products, orders, etc.
  |   If omitted and permissions allow site-wise posts are displayed.
  | * OBJID         - REQUIRED; might be optional depending on permissions.
  |   This compliments TYPE - e.g. ID of order or product (i.e. comments).
  |   If omitted and permissions allow displays posts belonging to all
  |   objects of particular TYPE.
  | * parent=       - optional; can be of 3 value types:
  |   1. Empty string or when omitted - shows all posts of TYPE and OBJID
  |   regardless of their parents. This includes root posts and replies.
  |   2. Zero - shows root posts (parentless).
  |   3. Number - shows posts that are replies directly to this parent post.
  |--------------------------------------------------------------------*/
  function get_index($type = null, $object = null) {
    $object = (int) $object;
    $parent = $this->in('parent', null);

    if (!$this->accessible('list', $object, $parent)) {
      return false;
    }

    $rows = Post
      ::with('author')
      ->where('type', '=', $type)
      ->where('object', '=', $object)
      ->order_by('created_at', 'desc');

    "$parent" === '' or $rows->where('parent', '=', $parent ?: null);

    $rows = $rows->get();

    if ($rows and !$this->can('post.hidefiles')) {
      $files = File::fromLists('post', prop('id', $rows));

      $rows = S($rows, function ($model) use ($files) {
        $attachments = (array) array_get($files, $model->id);
        return compact('attachments') + $model->withHTML()->to_array();
      });
    }

    return compact('rows');
  }

  /*---------------------------------------------------------------------
  | GET post/add /TYPE [/OBJID]
  |
  | Shows posting form.
  |----------------------------------------------------------------------
  | * TYPE          - REQUIRED; see GET post/index for details.
  | * OBJID         - REQUIRED or optional; see GET post/index for details.
  | * parent=       - REQUIRED or optional; either a falsy value (to create
  |   a root post) or a numeric ID of post being replied to.
  |--------------------------------------------------------------------*/
  function get_add($type = null, $object = null) {
    if (!$type) {
      return E_INPUT;
    } else {
      $hidden = array('parent' => $this->in('parent', 0));
      $canAttach = !$this->can('post.attach.deny');
      return compact('type', 'object', 'hidden', 'canAttach');
    }
  }

  /*---------------------------------------------------------------------
  | POST post/add /TYPE [/OBJID]
  |
  | Adds a new post with possible attachments in form upload.
  -----------------------------------------------------------------------
  | * TYPE          - REQUIRED; see GET post/index for details.
  | * OBJID         - REQUIRED or optional; see GET post/index for details.
  | * parent=       - REQUIRED or optional; either a falsy value (to create
  |   a root post) or a numeric ID of post being replied to.
  | * title= TITLE  - optional; post title.
  | * body=MSG      - optional; post body. Is REQUIRED when uploading no files.
  | * attach[]=FILE - optional; uploaded files to attach to this post.
  |--------------------------------------------------------------------*/
  function post_add($type = null, $object = null) {
    $result = $this->ajax(func_get_args());
    return $result === false ? false : $this->back();
  }

  function ajax_post_add($type = null, $object = null) {
    $object = (int) $object;
    $parent = $this->in('parent', 0) ?: null;
    $eventOptions = compact('type', 'object') + array('block' => $this);

    if (!$this->in('body', '')) {
      $body = Event::until('post.bodyless', array($eventOptions));

      if (isset($this->input)) {
        $this->input['body'] = $body;
      } else {
        Input::merge(compact('body'));
      }
    }

    $valid = Validator::make($this->in(), array(
      'parent'            => 'int',
      'title'             => 'max:50',
      'body'              => 'required',
    ));

    if (!$type) {
      return E_INPUT;
    } elseif (!$this->accessible('add', $object, $parent)) {
      return false;
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $model = with(new Post)
        ->fill_raw(Input::only(array('title', 'body')))
        ->fill_raw(compact('type') + array(
          'object'        => $object,
          'parent'        => $parent,
          'flags'         => $this->can('manager') ? 'manager' : '',
          'html'          => Post::format( $this->in('body') ),
          'author'        => $this->user()->id,
          'ip'            => Request::ip(),
        ));

      $eventOptions['post'] = $model = Event::insertModel($model, 'post');
      if (!$model) { return E_SERVER; }

      try {
        $attachments = array();
        Event::fire('post.attach', array(&$attachments, $eventOptions));
      } catch (\Exception $e) {
        $model->delete();
        throw $e;
      }

      $eventOptions += compact('attachments');
      Event::fire('post.added', array($eventOptions));

      if ($type and $object and $object = $model->object()) {
        Event::fire('post.object_ref', array(&$object, $eventOptions));
        ($object instanceof \Eloquent) and $object->save();
      }

      return $model;
    }
  }
}