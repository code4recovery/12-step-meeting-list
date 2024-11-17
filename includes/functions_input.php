<?php

/**
 * render an input field
 * used by the other functions in this file
 * 
 * @param mixed $attributes
 * @return void
 */
function tsml_input($attributes = [])
{
    echo '<input';
    foreach ($attributes as $key => $value) {
        if ($value) {
            echo ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
    }
    echo '>';
}

/**
 * render a date input field
 * 
 * @param mixed $name
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_date($name, $value = '', $attributes = [])
{
    tsml_input(array_merge(['id' => $name, 'name' => $name, 'type' => 'date', 'value' => $value], $attributes));
}

/**
 * render an email input field
 * 
 * @param mixed $name
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_email($name, $value = '', $attributes = [])
{
    tsml_input(array_merge(['id' => $name, 'name' => $name, 'type' => 'email', 'value' => $value], $attributes));
}

/**
 * render a hidden input field
 * 
 * @param mixed $name
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_hidden($name, $value = '', $attributes = [])
{
    tsml_input(array_merge(['id' => $name, 'name' => $name, 'type' => 'hidden', 'value' => $value], $attributes));
}

/**
 * render a submit button
 * 
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_submit($value, $attributes = ['class' => 'button'])
{
    tsml_input(array_merge(['type' => 'submit', 'value' => $value], $attributes));
}

/**
 * render a text input field
 * @param mixed $name
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_text($name, $value = '', $attributes = [])
{
    tsml_input(array_merge(['id' => $name, 'name' => $name, 'type' => 'text', 'value' => $value], $attributes));
}

/**
 * render a url field
 * 
 * @param mixed $name
 * @param mixed $value
 * @param mixed $attributes
 * @return void
 */
function tsml_input_url($name, $value = '', $attributes = ['placeholder' => 'https://'])
{
    tsml_input(array_merge(['id' => $name, 'name' => $name, 'type' => 'url', 'value' => $value], $attributes));
}
