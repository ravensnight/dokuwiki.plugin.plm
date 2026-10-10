<?php

abstract class Provider {

    /**
     * Provide some data using a given context.
     * @param array $context the param array
     */
    public abstract function provide(array $context) : array;
}
