# Dashboard Module Structure

## Current decisions

- Keep `users` for authentication and dashboard access only.
- Keep `employees` as the clinic HR or staff profile that can optionally be linked to a `user`.
- Use a one-to-one optional link through `employees.user_id`.
- Keep session data in the database so active sessions can be listed and revoked.

## Why `users` and `employees` stay separate

- Not every employee needs a login.
- Not every login will always be an employee.
- This keeps authentication concerns out of the HR or staff domain.
- It leaves room for future patient or external access without polluting the `employees` table.

## Initial dashboard modules

- `Auth`
  Handles login, password reset, profile, and active sessions.
- `User Management`
  Handles internal user accounts, activation, and dashboard access.
- `Employees`
  Handles staff profile data that is linked to a user only when needed.

## Planned module expansion

- `Clinic Settings`
- `Branches`
- `Doctors`
- `Patients`
- `Queue`
- `Medical Records`
- `Pharmacy`
- `Billing`
- `Reports`
