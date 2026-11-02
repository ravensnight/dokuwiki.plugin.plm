# Plan for PartItemVariant Class Implementation

## Overview
Create a DAO class `PartItemVariant` to work with the `plm_part_item_variant` table, following the same patterns and mechanisms as the existing `PartItemRef` class.

## Implementation Details

### Class Structure
- Extend `DbObject` base class
- Public properties: `partItemId` and `variantId` (both integers)
- Follow the same naming conventions as other DAO classes

### Methods to Implement
1. **Constructor and assign()** - Initialize object from database fields
2. **byPK()** - Get a specific record by primary key
3. **byPartItemId()** - Get all variants assigned to a specific part item
4. **byVariantId()** - Get all part items assigned to a specific variant
5. **entries()** - Get all records from the table
6. **hasVariant()** - Check if a variant is assigned to a part item
7. **assignVariant()** - Assign a variant to a part item
8. **removeVariant()** - Remove a variant assignment from a part item
9. **save()** - Update or create an object
10. **delete()** - Delete the referenced object
11. **fetchPartItem()** - Get the part item this variant assignment refers to
12. **fetchVariant()** - Get the variant this assignment refers to

## Key Considerations
- Follow the same patterns as `PartItemRef` for consistency
- Use proper database connection through `PlmDB::get()`
- Implement proper error handling and null checking
- Use the same SQL query patterns and naming conventions
- Maintain compatibility with the existing data structures