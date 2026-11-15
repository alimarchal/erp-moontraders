<?php

it('renders the compact cash notes inputs', function () {
    $view = $this->blade('<x-cash-notes />');

    $view->assertSee('name="denom[5000]"', false)
        ->assertSee('name="denom[10]"', false)
        ->assertSee('name="denom[coins]"', false);
});

it('uses the given name prefix', function () {
    $this->blade('<x-cash-notes name="expense_denom" />')
        ->assertSee('name="expense_denom[1000]"', false);
});
