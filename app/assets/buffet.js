document.addEventListener('DOMContentLoaded', () => {
  const dishItems = document.querySelectorAll('[data-dish-id]');
  const shelves = document.querySelectorAll('.shelf[data-shelf-id]');
  const message = document.querySelector('.buffet-message');
  const trash = document.querySelector('.buffet-trash');
  let draggedPlacementId = null;
  let draggedDishEl = null;

  const bindDragSource = (item) => {
    item.addEventListener('dragstart', (event) => {
      if (item.dataset.placementId) {
        draggedPlacementId = item.dataset.placementId;
        draggedDishEl = item;
        event.dataTransfer.setData('placementId', item.dataset.placementId);
        event.dataTransfer.effectAllowed = 'move';
        return;
      }

      event.dataTransfer.setData('dishId', item.dataset.dishId);
      draggedDishEl = item;
      event.dataTransfer.effectAllowed = 'copy';
    });
    item.addEventListener('dragend', () => {
      draggedPlacementId = null;
      draggedDishEl = null;
    });
  };

  const updateCollisionState = (shelf, event) => {
    if (!draggedDishEl) {
      shelf.classList.remove('is-colliding', 'is-available');
      return;
    }

    const shelfRect = shelf.getBoundingClientRect();
    const dropX = Math.round(event.clientX - shelfRect.left);
    const dropYFromBottom = Math.round(shelfRect.bottom - event.clientY);
    const targetDishEl = event.target.closest('.placed-dish');

    if (targetDishEl) {
      const targetIsStacked = targetDishEl.dataset.isStacked === '1';
      const draggedIsStacked = draggedDishEl.dataset.isStacked === '1';
      const sameType = targetDishEl.dataset.dishType === draggedDishEl.dataset.dishType;
      if (targetIsStacked && draggedIsStacked && sameType) {
        shelf.classList.remove('is-colliding');
        shelf.classList.add('is-available');
        return;
      }
    }

    const width = Number(draggedDishEl.dataset.width || draggedDishEl.getBoundingClientRect().width);
    const height = Number(draggedDishEl.dataset.height || draggedDishEl.getBoundingClientRect().height);
    const clampedX = Math.max(0, Math.min(Math.round(dropX), shelfRect.width - width));
    const clampedY = Math.max(0, Math.min(Math.round(dropYFromBottom - height), shelfRect.height - height));

    const rect = {
      left: clampedX,
      right: clampedX + width,
      bottom: clampedY,
      top: clampedY + height,
    };

    const overlap = Array.from(shelf.querySelectorAll('.placed-dish')).some((item) => {
      if (draggedPlacementId && item.dataset.placementId === draggedPlacementId) {
        return false;
      }
      const itemLeft = Number(item.style.left.replace('px', ''));
      const itemBottom = Number(item.style.bottom.replace('px', ''));
      const itemWidth = Number(item.dataset.width || item.getBoundingClientRect().width);
      const itemHeight = Number(item.dataset.height || item.getBoundingClientRect().height);

      const itemRect = {
        left: itemLeft,
        right: itemLeft + itemWidth,
        bottom: itemBottom,
        top: itemBottom + itemHeight,
      };

      return !(rect.right <= itemRect.left
        || rect.left >= itemRect.right
        || rect.top <= itemRect.bottom
        || rect.bottom >= itemRect.top);
    });

    shelf.classList.toggle('is-colliding', overlap);
    shelf.classList.toggle('is-available', !overlap);
  };

  dishItems.forEach((item) => {
    bindDragSource(item);
  });

  shelves.forEach((shelf) => {
    shelf.addEventListener('dragover', (event) => {
      event.preventDefault();
      event.dataTransfer.dropEffect = draggedPlacementId ? 'move' : 'copy';
      updateCollisionState(shelf, event);
    });

    shelf.addEventListener('dragleave', (event) => {
      if (!shelf.contains(event.relatedTarget)) {
        shelf.classList.remove('is-colliding', 'is-available');
      }
    });

    shelf.addEventListener('drop', async (event) => {
      event.preventDefault();
      const shelfId = shelf.dataset.shelfId;
      const shelfRect = shelf.getBoundingClientRect();
      const dropX = Math.round(event.clientX - shelfRect.left);
      const dropYFromBottom = Math.round(shelfRect.bottom - event.clientY);
      const targetDishEl = event.target.closest('.placed-dish');

      try {
        if (targetDishEl && draggedDishEl) {
          const targetIsStacked = targetDishEl.dataset.isStacked === '1';
          const draggedIsStacked = draggedDishEl.dataset.isStacked === '1';
          const sameType = targetDishEl.dataset.dishType === draggedDishEl.dataset.dishType;

          if (targetIsStacked && draggedIsStacked && sameType) {
            const targetPlacementId = targetDishEl.dataset.placementId;

            if (draggedPlacementId) {
              if (draggedPlacementId === targetPlacementId) {
                draggedPlacementId = null;
                draggedDishEl = null;
                return;
              }

              const response = await fetch('/api/stacks/merge', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                  sourcePlacementId: Number(draggedPlacementId),
                  targetPlacementId: Number(targetPlacementId),
                  position: 'top',
                }),
              });

              if (!response.ok) {
                if (message) {
                  message.textContent = 'Failed to stack dish.';
                }
                draggedPlacementId = null;
                draggedDishEl = null;
                return;
              }

              const payload = await response.json();
              const sourcePlacement = payload.placements.find((p) => String(p.id) === String(draggedPlacementId));
              if (sourcePlacement) {
                draggedDishEl.style.left = `${sourcePlacement.x}px`;
                draggedDishEl.style.bottom = `${sourcePlacement.y}px`;
                shelf.appendChild(draggedDishEl);
              }

              draggedPlacementId = null;
              draggedDishEl = null;
              if (message) {
                message.textContent = '';
              }
              shelf.classList.remove('is-colliding', 'is-available');
              return;
            }

            const dishId = draggedDishEl.dataset.dishId;
            const response = await fetch(`/api/shelves/${shelfId}/placements`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                shelfId: Number(shelfId),
                dishId: Number(dishId),
                targetPlacementId: Number(targetPlacementId),
              }),
            });

            if (!response.ok) {
              if (response.status === 409) {
                if (message) {
                  message.textContent = 'No space available on this shelf.';
                }
                return;
              }

              if (message) {
                message.textContent = 'Failed to stack dish.';
              }
              return;
            }

            const payload = await response.json();
            const placed = document.createElement('img');
            placed.src = draggedDishEl.dataset.image;
            placed.alt = draggedDishEl.alt || 'Dish';
            placed.className = 'placed-dish';
            placed.draggable = true;
            placed.dataset.placementId = payload.id;
            placed.dataset.dishId = dishId;
            placed.dataset.dishType = draggedDishEl.dataset.dishType;
            placed.dataset.isStacked = draggedDishEl.dataset.isStacked;
            placed.dataset.image = placed.src;
            placed.dataset.width = payload.width;
            placed.dataset.height = payload.height;
            placed.style.left = `${payload.x}px`;
            placed.style.bottom = `${payload.y}px`;
            placed.style.width = `${payload.width}px`;
            placed.style.height = `${payload.height}px`;
            shelf.appendChild(placed);
            bindDragSource(placed);

            if (message) {
              message.textContent = '';
            }
            shelf.classList.remove('is-colliding', 'is-available');
            return;
          }
        }

        if (draggedPlacementId) {
          const placedEl = document.querySelector(`[data-placement-id="${draggedPlacementId}"]`);
          if (!placedEl) {
            draggedPlacementId = null;
            draggedDishEl = null;
            return;
          }

          const width = placedEl.getBoundingClientRect().width;
          const height = placedEl.getBoundingClientRect().height;
          const clampedX = Math.max(0, Math.min(Math.round(dropX), shelfRect.width - width));
          const clampedY = Math.max(0, Math.min(Math.round(dropYFromBottom - height), shelfRect.height - height));

          const response = await fetch(`/api/placements/${draggedPlacementId}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              shelfId: Number(shelfId),
              x: clampedX,
              y: clampedY,
            }),
          });

          if (!response.ok) {
            if (response.status === 409) {
              if (message) {
                message.textContent = 'Collision detected.';
              }
              draggedPlacementId = null;
              draggedDishEl = null;
              return;
            }

            if (message) {
              message.textContent = 'Failed to move dish.';
            }
            draggedPlacementId = null;
            draggedDishEl = null;
            return;
          }

          placedEl.style.left = `${clampedX}px`;
          placedEl.style.bottom = `${clampedY}px`;
          shelf.appendChild(placedEl);

          draggedPlacementId = null;
          draggedDishEl = null;
          if (message) {
            message.textContent = '';
          }
          shelf.classList.remove('is-colliding', 'is-available');
          return;
        }

        const dishId = event.dataTransfer.getData('dishId');
        if (!dishId) {
          return;
        }

        const sourceDishEl = draggedDishEl || document.querySelector(`.buffet-items [data-dish-id="${dishId}"]`);
        if (!sourceDishEl) {
          return;
        }

        const response = await fetch(`/api/shelves/${shelfId}/placements`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            shelfId: Number(shelfId),
            dishId: Number(dishId),
          }),
        });

        if (!response.ok) {
          if (response.status === 409) {
            if (message) {
              message.textContent = 'No space available on this shelf.';
            }
            return;
          }

          if (message) {
            message.textContent = 'Failed to place dish.';
          }
          return;
        }

        const payload = await response.json();
        const placed = document.createElement('img');
        placed.src = sourceDishEl.dataset.image || sourceDishEl.src;
        placed.alt = sourceDishEl.alt || 'Dish';
        placed.className = 'placed-dish';
        placed.draggable = true;
        placed.dataset.placementId = payload.id;
        placed.dataset.dishId = dishId;
        placed.dataset.dishType = sourceDishEl.dataset.dishType;
        placed.dataset.isStacked = sourceDishEl.dataset.isStacked;
        placed.dataset.image = placed.src;
        placed.dataset.width = payload.width;
        placed.dataset.height = payload.height;
        placed.style.left = `${payload.x}px`;
        placed.style.bottom = `${payload.y}px`;
        placed.style.width = `${payload.width}px`;
        placed.style.height = `${payload.height}px`;
        shelf.appendChild(placed);
        bindDragSource(placed);

        if (message) {
          message.textContent = '';
        }
        shelf.classList.remove('is-colliding', 'is-available');
      } catch (error) {
        if (message) {
          message.textContent = 'Failed to place dish.';
        }
        shelf.classList.remove('is-colliding', 'is-available');
      }
    });
  });

  if (trash) {
    trash.addEventListener('dragover', (event) => {
      if (!draggedPlacementId) {
        return;
      }
      event.preventDefault();
      trash.classList.add('is-active');
      event.dataTransfer.dropEffect = 'move';
    });

    trash.addEventListener('dragleave', () => {
      trash.classList.remove('is-active');
    });

    trash.addEventListener('drop', async (event) => {
      event.preventDefault();
      if (!draggedPlacementId) {
        return;
      }

      try {
        const response = await fetch(`/api/placements/${draggedPlacementId}`, {
          method: 'DELETE',
        });

        if (!response.ok && response.status !== 404) {
          if (message) {
            message.textContent = 'Failed to delete dish.';
          }
          return;
        }

        if (response.status === 204) {
          const placedEl = document.querySelector(`[data-placement-id="${draggedPlacementId}"]`);
          if (placedEl) {
            placedEl.remove();
          }
        }

        if (message) {
          message.textContent = '';
        }
      } catch (error) {
        if (message) {
          message.textContent = 'Failed to delete dish.';
        }
      } finally {
        trash.classList.remove('is-active');
        draggedPlacementId = null;
        draggedDishEl = null;
      }
    });
  }
});
