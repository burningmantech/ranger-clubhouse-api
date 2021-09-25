<?php

namespace App\View\Components;

use Illuminate\View\Component;

class HtmlEmail extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $isPublicEmail = true)
    {
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.html-email');
    }
}
