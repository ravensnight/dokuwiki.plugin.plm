# PLM DokuWiki Plugin

State-based frontend components for DokuWiki Struct.

The PLM plugin provides reusable UI macros for displaying,
selecting, editing and linking Struct records while preserving
application state through a compact URL parameter.

---

# OVERVIEW

PLM consists of three main macros:

- `plm:table`
  Display and manage lists of Struct records.

- `plm:select`
  Select exactly one Struct record and expose it as state.

- `plm:form`
  Display and edit a single Struct record.

All components communicate through the PLM state system.

Example:

```text
parts.current._pk = 42
parts.current.ipn = PRD-1234
```

State is automatically transported through:

```text
?plm=eyJwYXJ0cyI6eyJjdXJyZW50Ijp7...
```

---

# PLM STATE MODEL

State is stored as:

```text
context.scope.field
```

Example:

```text
parts.current._pk
parts.current.ipn

parts.filter.ipn
```

Scopes:

| Scope | Meaning |
|---------|----------|
| current | currently selected record |
| filter | active filter values |

Examples:

```text
%parts.current._pk
%parts.current.ipn

%parttable.filter.ipn
```

---

# FILTER EXPRESSIONS

Filters use Struct syntax:

```text
field=value
field=*text*
field~text
```

PLM references are allowed:

```text
_pk=%parts.current._pk

part._pk=%parts.current._pk
```

Request parameters:

```text
&_pk
&ipn
```

---

# plm:table

Display a list of Struct records.

## Syntax

```text
/plm:table > <context> | <schema>
...
/plm
```

Example:

```text
/plm:table > parts | plm_part

cols: _pk, ipn, description

filter: ipn

create: ipn, description

delete: "Delete"

details: "Details" :intern:plm:partedit

/plm
```

---

## Parameters

### cols:

Displayed columns.

Example:

```text
cols: _pk, ipn, description
```

Template columns:

```text
cols: ipn, @link
```

---

### template:

Define text templates.

Example:

```text
template: link "Open" [[ :intern:part?id=$_pk ]]
```

Usage:

```text
cols: ipn, @link
```

---

### filter:

Enable filter fields.

Example:

```text
filter: ipn, description
```

State:

```text
parts.filter.ipn
```

---

### create:

Enable inline create row.

Example:

```text
create: ipn, description
```

---

### delete:

Show delete button.

Example:

```text
delete: "Delete"
```

Selected row:

```text
parts.current._pk
```

---

### details:

Show navigation button.

Example:

```text
details: "Open" :intern:plm:partedit
```

Generated URL:

```text
/intern/plm/partedit?plm=...
```

---

# plm:select

Select exactly one Struct record.

## Syntax

```text
/plm:select > <context> | schema[filter]
content
/plm
```

Example:

```text
/plm:select > selectedpart | plm_part[_pk=%parts.current._pk]

## Editing:
$ipn

Description:
$description

/plm
```

---

## State generated

```text
selectedpart.current._pk
selectedpart.current.ipn
selectedpart.current.description
```

---

## Variables

Struct fields:

```text
$ipn
$description
$_pk
```

---

## Empty message

```text
/plm:select > part | plm_part[_pk=999] "No item selected."
...
/plm
```

---

# plm:form

Display or edit a single Struct record.

## Syntax

```text
/plm:form > <context> | schema[filter]
...
/plm
```

Example:

```text
/plm:form > partform | plm_part[_pk=%parts.current._pk]

field: ipn readonly
field: description

action: update "Update" :intern:plm:partedit
action: delete "Delete" :intern:plm:partmgr

/plm
```

---

## field:

Limit visible fields.

Example:

```text
field: ipn
field: description
```

Readonly:

```text
field: ipn readonly
```

---

## action:

Supported actions:

```text
create
update
delete
redirect
```

Example:

```text
action: update "Save"
```

Redirect:

```text
action: update "Save" :intern:plm:partedit
```

PLM state is automatically preserved.

---

## Create mode

Without filter:

```text
/plm:form > newpart | plm_part

field: ipn
field: description

action: create "Create"

/plm
```

---

# PLM REFERENCES

---

## Request values

```text
&_pk
&ipn
```

Example:

```text
plm_part[_pk=&_pk]
```

---

## State references

```text
%parts.current._pk
%parts.current.ipn
```

Example:

```text
part._pk=%parts.current._pk
```

---

## Template variables

Inside templates:

```text
$_pk
$ipn
$description
```

Example:

```text
template: link "Open" [[ :page?id=$_pk ]]
```

---

# EXAMPLE APPLICATION

Part manager:

```text
/plm:table > parts | plm_part

cols: ipn, description

filter: ipn

create: ipn, description

delete: "Delete"

details: "Edit" :intern:plm:partedit

/plm
```

Editor page:

```text
/plm:form > partform | plm_part[_pk=%parts.current._pk]

field: ipn readonly
field: description

action: update "Update" :intern:plm:partedit
action: delete "Delete" :intern:plm:partmgr

/plm
```

---

# INTERNAL URL FORMAT

Example:

```text
http://wiki/doku.php/intern:plm:partedit?plm=...
```

The `plm` parameter contains:

```text
context
scope
current record
filter values
navigation state
```

No session storage is required.

---

# DESIGN GOALS

- Stateless
- URL-based navigation
- Struct integration
- Nested views
- Bookmarkable pages
- No server session
- Reusable macros
- Declarative syntax
