# TODO

## Entity

### Shelf
> Описує фізичні межі полиці.

- id (int)
- width (int) — наприклад, у мм або умовних одиницях.
- height (int) — відстань до наступної полиці.
- is_empty (bool)

### Dish
> Типи посуду

- id (int)
- name (string) — "Тарілка глибока", "Горнятко жовте".
- width (int)
- height (int) — габарити об'єкта.
- is_stacked (bool)

### ShelfPlacement
> Пов'язує посуд з полицею.

- id (int)
- shelf_id (Relation)
- dish_id (Relation)
- position_x (int/float) — відстань від лівого краю.
- position_y (int/float) — відстань від низу полиці (if is_stacked == 1).

## Coordinate

- x for each  