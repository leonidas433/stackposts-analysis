<?php

namespace Modules\AppAppearance\Livewire;

use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Appearance extends Component
{
    public $page = '';

    public function render()
    {
        if ($this->page == '') {
            $route_name = Route::getCurrentRoute()->getName();
            $route_name = str_replace('.', '::', $route_name);
            $page_data = view($route_name.'::index');
        } else {
            $page_data = view($this->page.'::index');
        }

        return view('appappearance::index', ['page_data' => $page_data]);
    }

    public function goto($page = 'dashboard')
    {
        $this->page = $page;
    }
}
