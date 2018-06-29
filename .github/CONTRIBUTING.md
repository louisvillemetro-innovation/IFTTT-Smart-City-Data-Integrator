
# Contributing to IFTTT-Smart-Louisville

## Issues

Issues submitted to this repo should follow one of the following two models:

### Bug Report

A bug report should explain the problem as specifically as possible along with steps to reproduce. For instance,

> The API is broken

...is not a useful bug report.

> When IFTTT polls for new information, it receives a 500 error, dumps the contents of the POST and no data is retrieved. This does not happen if I poll the endpoint without the proper authorization key. I am using version 1.1 of the IFTTT-Smart-Louisville API

...is a very useful bug report!

#### Bug Report Template:
```
**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
A clear and concise description of what you expected to happen.

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Desktop (please complete the following information):**
 - OS: [e.g. iOS]
 - Browser [e.g. chrome, safari]
 - Version [e.g. 22]

**Smartphone (please complete the following information):**
 - Device: [e.g. iPhone6]
 - OS: [e.g. iOS8.1]
 - Browser [e.g. stock browser, safari]
 - Version [e.g. 22]

**Additional context**
Add any other context about the problem here.
```

### New Feature

Any new feature or other change in functionality that is NOT a bug in existing functionality should be expressed as a user story, with optional additional context. A user story should follow the general format of 

> _As a [user role], I want to [execute an action] so that I can [achieve an outcome]._

Mark both the user story and additional context with headings identifying them as such. 

Writing everything out as a traditional user story may seem tedious, but it becomes much easier to determine whether an issue is complete when there is a clear user story to test against.

#### New Feature Template
```
**Is your feature request related to a problem? Please describe.**
A clear and concise description of what the problem is. Ex. I'm always frustrated when [...]

**Describe the solution you'd like**
A clear and concise description of what you want to happen.

**Describe alternatives you've considered**
A clear and concise description of any alternative solutions or features you've considered.

**Additional context**
Add any other context or screenshots about the feature request here.

```

## Pull requests

Pull requests should include sufficient context for a maintainer to open them for the first time and understand clearly what if any action needs to be taken. This means a pull request should contain in its initial description, at minimum:

1. Either:
  * a link to a clearly-defined issue in either the issue queue or a specific implementation project (preferable), or
  * a clearly written explanation of the issue the pull request adresses, ideally formatted as a user story.
2. An acceptance test with easily-reproducable and verifiable steps formatted as github tasks. In most cases, the issue the pull request references will include acceptance criteria that can be cut and pasted in.

### Pull Request Template
```
## Description
ref: [issue#]

## Acceptance Test

```

Please use a reasonably descriptive *title* as well. "Updating routes" is not a helpful title; "Adding patch to integration to address argument bug" is a helpful title!

If a pull request is simply being created for QA purposes or should for some other reason NOT be merged, explain this in the description and add a "don't merge" tag.

### Changelog Guidelines

Pull requests must include a new line in CHANGELOG.txt before being merged into the master branch explaining what has changed. Make sure to point out any dependencies added, removed, new requirements or patches applied.
