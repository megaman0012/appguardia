<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Input extends Component{
    public $id;
    public $label;
    public $class;
    public $type;
    public $value;
    public $placeholder;
    public $required;
    public $disabled;
    public $readonly;
    public $name;
    public $options;
    public $charlimit;

    public function __construct( array $data = [] ) {
        $this->id = $data['id'] ?? null;
        $this->label = $data['label'] ?? null;
        $this->class = $data['class'] ?? null;
        $this->type = $data['type'] ?? 'text';
        $this->value = $data['value'] ?? null;
        $this->placeholder = $data['placeholder'] ?? null;
        $this->required = $data['required'] ?? false;
        $this->disabled = $data['disabled'] ?? false;
        $this->readonly = $data['readonly'] ?? false;
        $this->name = $data['name'] ?? null;
        $this->options = $data['options'] ?? [];
        $this->charlimit = $data['charlimit'] ?? 0;
    }

    public function render(){
        return view('components.input');
    }
}
