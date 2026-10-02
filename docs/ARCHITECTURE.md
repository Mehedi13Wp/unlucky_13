# Unlucky 13 Architecture

## Purpose

Unlucky 13 is a private, local-first lead generation and outreach worker. The system is designed to automate repetitive research and outreach preparation while keeping targeting relevant, auditable, rate-limited, and review-first.

## Application boundaries

### Laravel application

Laravel owns the control plane:

- authentication and settings
- campaign configuration
- lead database and lifecycle
- outreach review queue
- sending policy and suppression state
- reporting and activity history
- APIs consumed by workers

### Python worker

The Python worker will own long-running discovery and analysis tasks:

- permitted/public business discovery
- website inspection
- need-signal extraction
- qualification support
- contact discovery from legitimate public business sources
- AI-assisted personalization and reply classification

The worker will communicate with Laravel through explicit APIs and will not write directly to application tables once the API boundary is introduced.

## Core lifecycle

Campaign -> Discover -> Analyze -> Qualify -> Contact Discovery -> Draft Outreach -> Review -> Send -> Track -> Classify Reply -> Alert

## Safety defaults

- review before send is enabled by default
- automatic sending is disabled by default
- daily outreach limits are stored per campaign
- leads are deduplicated per campaign/domain
- personalization must be based on observed facts
- unsubscribe, bounce, suppression, and reply handling are first-class lifecycle states
- secrets remain in environment configuration and never in source control

## Initial domain model

### Campaign

Defines the service offer, target market, geography, qualification rules, daily outreach limit, and sending policy.

### Lead

Represents a discovered business and its analysis state. Contact people, messages, suppression records, and activity history will be separate domain records in later milestones.

## Milestones

1. Core campaign and lead foundation
2. Authentication and application shell
3. Dashboard and campaign management
4. Lead management and discovery API
5. Analysis and qualification pipeline
6. Contact discovery and outreach review
7. Email delivery, replies, suppression, and alerts
8. Worker scheduling, observability, tests, and production hardening
