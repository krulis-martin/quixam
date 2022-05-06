<?php

declare(strict_types=1);

namespace App\Security;

interface IExternalAuthenticator
{
    /**
     * Perform internal initialization.
     * This action is intentionally deferred, since the constructor is invoked even when the authenticator is not used.
     */
    public function initialize(): void;

    /**
     * Perform the authentication.
     * @return bool true if the authentication was successfull
     */
    public function authenticate(): bool;

    /**
     * Check whether a new authentication was performed.
     * This function may be called only after successfull authentication.
     * @return bool If false, the authentication was accepted based on long-lasting tokens (e.g., cookie).
     */
    public function isFresh(): bool;

    /**
     * Get the external identifier of the user.
     * This function may be called only after successfull authentication.
     * @return string|null
     */
    public function getUserId(): ?string;

    /**
     * Get the first name of the user.
     * This function may be called only after successfull authentication.
     * @return string|null
     */
    public function getUserFirstName(): ?string;

    /**
     * Get the last name of the user.
     * This function may be called only after successfull authentication.
     * @return string|null
     */
    public function getUserLastName(): ?string;

    /**
     * Get all email addresses of the user (the first address should be the primary one).
     * This function may be called only after successfull authentication.
     * @return string[] list of addresses
     */
    public function getUserEmails(): array;

    /**
     * Suggests a role based on users additional attributes
     * This function may be called only after successfull authentication.
     * @return string a role identifier or an empty string if the suggestion cannot be made
     */
    public function getUserSuggestedRole(): string;

    /**
     * Erase all related tokens (cookies, sessions...).
     */
    public function logout(): void;
}
