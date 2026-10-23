# AI Usage

## Tools Used

- **ChatGPT:** Used to understand the assignment requirements, plan the project structure, and explain Laravel concepts.
- **AI Coding Assistant:** Used to assist with code generation, debugging, and understanding the implementation.

## How AI Helped

1. Helped organize the REST APIs, database tables, and project file structure.
2. Assisted with implementing premium calculation, policy issuance, duplicate prevention, and timeout recovery.
3. Helped prepare test cases, API examples, and project documentation.

## An Incorrect Suggestion

An unsafe approach would be to retry a policy submission immediately after a timeout. The insurer might have already issued the policy even though the application did not receive a response. The safer approach is to check the mock insurer's status and reconcile the existing policy before retrying.

## How I Verified

- Reviewed the generated code against the assignment requirements.
- Checked API routes and request validation.
- Ran the available automated tests using `php artisan test`.
- Tested API requests using Postman.
- Reviewed duplicate-request handling, policy status transitions, and timeout recovery.

AI was used as a development assistant. I am responsible for understanding the submitted code and verifying its correctness.
