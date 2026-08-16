# Email verification is deliberately not enforced

`User` and `Staff` implement Laravel's `MustVerifyEmail`, but we decided to never enforce email verification at login. The customer's identity is proven by phone OTP (or, when registering by email, the OTP sent to that email), and staff accounts are created manually by admins where the email is often a placeholder.

A future reader will see `MustVerifyEmail` and assume verification should be enforced. It isn't, because email is not the identity anchor here — enforcing `email_verified_at` would lock out legitimate phone-OTP users who never touched email.
