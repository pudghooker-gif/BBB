<?php

namespace VanguardLTE\Support\Presenter;

trait PresentableTrait
{
    protected $presenterInstance;

    public function present()
    {
        $presenterClass = isset($this->presenter) ? $this->presenter : null;

        if (!$presenterClass || !class_exists($presenterClass)) {
            throw new PresenterException('Please set the $presenter property to your presenter path.');
        }

        if (!$this->presenterInstance instanceof $presenterClass) {
            $this->presenterInstance = new $presenterClass($this);
        }

        return $this->presenterInstance;
    }
}
