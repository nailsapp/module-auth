# Events
> Documentation is a WIP.


This module exposes the following events through the [Nails Events Service](https://github.com/nails/common/blob/master/docs/intro/events.md) in the `nails/module-invoice` namespace.

> Remember you can see all events available to the application using `nails events`


- [User](#user)
    - [Nails\Auth\Events::USER_CREATED](#user-created)
    - [Nails\Auth\Events::USER_MODIFIED](#user-modified)



## User

<a name="user-created"></a>
### `Nails\Auth\Events::USER_CREATED`

Fired when  user is created.

**Receives:**

> ```
> int $iId The ID of the user who was created
> ```


<a name="user-modified"></a>
### `Nails\Auth\Events::USER_MODIFIED`

Fired when a user is modified.

**Receives:**

> ```
> int $iId The ID of the user who was modified
> ```
