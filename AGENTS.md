# Repository workflow

- Use `master` as the integration branch.
- Before implementing a ticket, create `feature/<issue-number>-<short-name>` from current `master` and set its project status to In Progress.
- Keep each branch and pull request scoped to its ticket.
- Run checks relevant to the change, then open a pull request targeting `master` with acceptance evidence and `Closes #<issue-number>`.
- Do not merge pull requests without the user's instruction. Mark issues Done only after merge.
- Never commit secrets, `.env`, dependencies, logs, or build output.
