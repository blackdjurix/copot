<?php

namespace Copot\Core;

final class CurrentUserPasswordService
{
    public function __construct(
        private Auth $auth,
        private UserProvider $users,
        private PasswordHasher $passwords,
        private PasswordPolicy $policy,
        private ReauthenticationService $reauthentication,
        private SecurityEventService $securityEvents,
    ) {
    }

    public function change(string $password, string $confirmation): CurrentUserPasswordChangeResult
    {
        if (!$this->auth->check()) {
            return CurrentUserPasswordChangeResult::failure('unauthenticated');
        }

        $user = $this->auth->user();
        $sessionIdentity = $this->auth->durableSessionIdentity();
        if (!$user instanceof User || $sessionIdentity === null) {
            return CurrentUserPasswordChangeResult::failure('unauthenticated');
        }

        try {
            $this->reauthentication->requireRecentProof();
        } catch (ReauthenticationRequiredException) {
            return CurrentUserPasswordChangeResult::failure('reauthentication_required');
        }

        $errors = [];
        $policyError = $this->policy->validate($password, 'New password');
        if ($policyError !== null) {
            $errors['password'] = $policyError;
        }
        if ($confirmation !== $password) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            $this->securityEvents->recordCredentialChange($user->id(), $sessionIdentity, 'rejected');

            return CurrentUserPasswordChangeResult::failure('invalid_password', $errors);
        }

        try {
            $this->users->updatePasswordHash($user->id(), $this->passwords->make($password));
            $this->reauthentication->clearProof();
            $this->securityEvents->recordCredentialChange($user->id(), $sessionIdentity, 'success');

            return CurrentUserPasswordChangeResult::success();
        } catch (\Throwable) {
            $this->securityEvents->recordCredentialChange($user->id(), $sessionIdentity, 'failed');

            return CurrentUserPasswordChangeResult::failure('password_change_failed');
        }
    }
}
