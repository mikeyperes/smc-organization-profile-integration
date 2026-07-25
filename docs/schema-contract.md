# Schema Contract

## Stable IDs

- website: `{home}/#website`
- primary organization: `{home}/#organization`
- secondary organization: `{organization permalink}#organization`
- current page: `{page permalink}#webpage`
- related nodes: deterministic paths below the owning Organization ID

## Organization Properties

SMC conditionally emits only populated values. Supported properties include:

`name`, `legalName`, `alternateName`, `url`, `mainEntityOfPage`, `description`, `slogan`, `foundingDate`, `dissolutionDate`, `foundingLocation`, `location`, `address`, `areaServed`, `email`, `telephone`, `contactPoint`, `sameAs`, `identifier`, `naics`, `isicV4`, `leiCode`, `taxID`, `vatID`, `duns`, `numberOfEmployees`, `founder`, `employee`, `parentOrganization`, `subOrganization`, `department`, `memberOf`, `brand`, `makesOffer`, `hasCredential`, `award`, `seeks`, `subjectOf`, `review`, `aggregateRating`, `logo`, and `image`.

## Identity and Editorial Links

Only URLs that identify the same organization are emitted as `sameAs`.

Press and milestone records are editorial evidence and become related Article or Event nodes referenced through `subjectOf`. They never become identity links.

## Ratings

Review nodes may exist without ratings as valid Schema.org reviews. `aggregateRating` is emitted only when at least one related testimonial has a numeric rating. No rating is inferred or fabricated.

## Media

All editable media fields store attachment IDs. Runtime media normalization adds canonical URLs, dimensions, alt text, titles, and captions. The graph uses ImageObject nodes so logo and gallery references resolve consistently.
