<?php

namespace App\Support;

final class ApiMessages
{
    public const UNAUTHENTICATED = 'Unauthenticated.';
    public const RESOURCE_NOT_FOUND = 'Resource not found.';
    public const VALIDATION_FAILED = 'The given data was invalid.';
    public const INVALID_CREDENTIALS = 'The provided credentials are incorrect.';
    public const INSUFFICIENT_STOCK = 'Insufficient stock for "%s": requested %d, available %d.';
    public const PRODUCT_UNAVAILABLE = 'One or more selected products are no longer available.';
    public const USER_REGISTERED = 'User registered successfully.';
    public const LOGGED_IN = 'Logged in successfully.';
    public const LOGGED_OUT = 'Logged out successfully.';
    public const TOKEN_REFRESHED = 'Token refreshed successfully.';
    public const INVALID_REFRESH_TOKEN = 'The refresh token is invalid or has expired.';
    public const PRODUCT_CREATED = 'Product created successfully.';
    public const PRODUCT_UPDATED = 'Product updated successfully.';
    public const PRODUCT_DELETED = 'Product deleted successfully.';
    public const ORDER_PLACED = 'Order placed successfully.';
    public const ORDER_NOT_FOUND = 'Order not found.';
}
