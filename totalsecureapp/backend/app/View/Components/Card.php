<?php

namespace App\View\Components;
use Illuminate\View\Component;
class Card extends Component{
    public $id;
    public $class;
    public function __construct( array $data = [] ){
        $this->id = $data['id'] ?? null;
        $this->class = $data['class'] ?? 'card-secondary card-outline';
    }
    public function render(){
        return view('components.card');
    }
}
