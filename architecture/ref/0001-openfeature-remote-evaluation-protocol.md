---
id: REF-0001
title: OpenFeature Remote Evaluation Protocol
verified: 2026-10-20
---

# REF-0001: OpenFeature Remote Evaluation Protocol

OFREP is OpenFeature's HTTP protocol between an application and a flag management system. An
application uses an OpenFeature SDK with the OFREP provider, and the provider talks to any server
that implements the protocol. A server that speaks OFREP needs no SDK of its own.

The protocol is an OpenAPI document, `service/openapi.yaml` in `open-feature/protocol`, licensed
Apache 2.0. It is at version 0.4.0 as of the verified date. It is pre-1.0, so it can still change.

## The two endpoints

Both are `POST`, both take a JSON body of `{"context": {...}}`, and both are the "core" set a server
must implement to be compatible.

| Endpoint | Used by | Returns |
| --- | --- | --- |
| `POST /ofrep/v1/evaluate/flags/{key}` | Server-side providers, a request per evaluation with that request's context | One evaluation |
| `POST /ofrep/v1/evaluate/flags` | Client-side providers, which evaluate every flag once for a fixed context and cache the result | `{"flags": [...]}`, an evaluation per flag |

The context is free-form. `targetingKey` is the one named property, a string identifying the
subject of the evaluation, and it is optional in the schema.

A successful evaluation carries `key`, `reason`, and usually `value` and `variant`, plus optional
`metadata` of string, number and boolean values. `value` may be a boolean, string, integer, float
or object. An evaluation with no `value` tells the provider to use the default written in the
application's code.

`reason` is one of `STATIC`, `TARGETING_MATCH`, `SPLIT`, `DISABLED` and `UNKNOWN`. OpenFeature's
wider specification has more reasons than this enum allows, `DEFAULT` among them.

## Errors

OFREP has its own error bodies, and they are not RFC 9457 problem details:

| Status | Body |
| --- | --- |
| `400` | `{"key", "errorCode", "errorDetails"}`, where `errorCode` is `PARSE_ERROR`, `TARGETING_KEY_MISSING`, `INVALID_CONTEXT` or `GENERAL` |
| `404` | `{"key", "errorCode": "FLAG_NOT_FOUND", "errorDetails"}` |
| `401`, `403` | No body defined |
| `429` | No body defined; `Retry-After` as seconds or a date |
| `500` | `{"errorDetails"}` |

The bulk endpoint fails as a whole with `{"errorCode", "errorDetails"}`. A single flag's failure
sits in the `flags` array beside the successes, so one broken flag does not fail the request.

`errorDetails` is described as human-readable text for logging and debugging. `errorCode` is the
part a provider acts on.

## Caching the bulk result

The bulk response carries an `ETag`, described as representing "the current state of all flags".
A client sends it back in `If-None-Match`, and when it still matches the server answers `304 Not
Modified` with no body.

That conflicts with HTTP. RFC 9110, section 13.1.2, says that when `If-None-Match` matches, the
server answers `304` for `GET` and `HEAD`, and `412 Precondition Failed` for every other method. A
`304` to a `POST` is outside HTTP's semantics, so generic HTTP tooling handles it the HTTP way.

The ETag is described in terms of the flags alone. The response also depends on the context in the
request body, and two contexts can evaluate differently against the same flags. A server that
derives the ETag from the flags alone will tell a client whose context changed that nothing has.

## Authentication

Two schemes are declared, both optional: `Authorization: Bearer <token>`, and an API key in an
`X-API-Key` header. The protocol leaves the meaning of the credential to the server.

## Change notifications

Version 0.4.0 adds optional `eventStreams` to the bulk response: server-sent event connections that
tell a client to fetch again. A `refetchEvaluation` event may carry an ETag or a last-modified
time, which the client passes back as the `flagConfigEtag` and `flagConfigLastModified` query
parameters. These are metadata for the server, not HTTP conditional headers. A client that is
given no event streams keeps polling.

## Sources

- `open-feature/protocol`, `service/openapi.yaml`, version 0.4.0:
  https://github.com/open-feature/protocol/blob/main/service/openapi.yaml
- The OFREP overview: https://openfeature.dev/docs/reference/other-technologies/ofrep/
- RFC 9110, HTTP Semantics, section 13.1.2: https://www.rfc-editor.org/rfc/rfc9110#section-13.1.2
