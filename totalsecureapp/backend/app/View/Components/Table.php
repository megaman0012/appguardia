<?php

namespace App\View\Components;
use Illuminate\View\Component;

class Table extends Component{
    public $id;
    public $class;
    public function __construct( $id = 'default', $class = 'default' ){
        $this->id = $id;
        $this->class = $class;
    }
    public function render(){
        return view('components.table');
    }
}
