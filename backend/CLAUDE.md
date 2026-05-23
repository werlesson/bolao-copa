# BolaoCopa - Laravel 13 Backend

## Stack
Laravel 13, PHP 8.3, PostgreSQL 16, Redis 7, Horizon, Sanctum, Socialite.
Docker-based infrastructure. No Supabase.

## Full context
Read ../prompt-final.md before writing any code.
It contains: full schema with comments, all business rules,
endpoints with exact behavior, jobs with pseudocode, and implementation order.

## Docker environment
- PHP-FPM app: internal port 9000
- Nginx: proxy on port 8000
- PostgreSQL: host "postgres", port 5432, database "bolao"
- Redis: host "redis", port 6379
- Horizon and Scheduler: separate containers in dev
- Production: Supervisor manages everything in a single container

## Required conventions
- PHP 8.3 � use PHP Attributes on Models (Laravel 13 feature)
- Form Requests for all validation
- API Resources for all responses (never return Model directly)
- Scheduler: use Schedule:: in routes/console.php (not Kernel.php)
- Redis cache with prefix "ranking:group:{id}"
- Return 422 for guess submitted after kickoff
- Return 403 for banned user attempting to join a group
- Return 403 for any forbidden operation on the global group
- Sanctum token always in httpOnly cookie, never in Authorization header

## Ranking tie-break rule
Sort order: total_points DESC → exact_scores DESC → LOWER(users.name) ASC.
Players with identical (total_points, exact_scores) share the same position (1224 pattern).
Within a tied group, players are listed alphabetically (case-insensitive).
  A 6pts 2ex → pos 1 | B 6pts 2ex → pos 1 | C 3pts 1ex → pos 3 | D 1pt 0ex → pos 4
  (A and B ordered A→B alphabetically within their tied group)
Implemented in RankingController::fetchRows() via JOIN on users + LOWER(users.name).
Never use sequential $i+1 positions.
