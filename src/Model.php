<?php

declare(strict_types=1);

namespace RssApp;

use Exception;

abstract class Model
{

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new Exception('Attempting to access unknown property');
    }

    /**
     * @throws Exception
     */
    public function __set(string $name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return $this;
        }

        throw new Exception('Attempting to access unknown property');
    }

}
