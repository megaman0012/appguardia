<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Modal extends Component{
    public $id;
    public $class;
    public $title;
    public $width;
    public function __construct($id = 'default', $class = 'default', $title = 'Default Modal', $width = '500px'){
        $this->id = $id;
        $this->class = $class;
        $this->title = $title;
        $this->width = $width;
    }
    public function render(){
        return view('components.modal');
    }
}
