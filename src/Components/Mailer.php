<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Utility\EmailUtilities;

class Mailer extends \Illuminate\Mail\Mailer
{
    /**
     * Render the given view.
     *
     * @param  string $view
     * @param  array  $data
     *
     * @return mixed
     */
    protected function getView($view, $data)
    {
        try {
            return $this->views->make($view, $data)->render();
        } catch (\InvalidArgumentException $e) {
            return EmailUtilities::applyDataToView($view, $data);
        }
    }
}