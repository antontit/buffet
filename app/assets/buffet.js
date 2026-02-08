document.addEventListener('DOMContentLoaded', () => {
  const dishItems = document.querySelectorAll('[data-dish-id]');
  const shelves = document.querySelectorAll('.shelf[data-shelf-id]');
  const message = document.querySelector('.buffet-message');
  const trash = document.querySelector('.buffet-trash');
  const state = {
    draggedPlacementId: null,
    draggedDishEl: null,
  };

  const setMessage = (text) => {
    if (!message) {
      return;
    }
    message.textContent = text;
  };

  const clearShelfState = (shelf) => {
    shelf.classList.remove('is-colliding', 'is-available');
  };

  const setShelfState = (shelf, isColliding) => {
    shelf.classList.toggle('is-colliding', isColliding);
    shelf.classList.toggle('is-available', !isColliding);
  };

  const parseTransferData = (event) => {
    const raw = event.dataTransfer.getData('text/plain');
    if (!raw) {
      return {};
    }
    try {
      return JSON.parse(raw);
    } catch (error) {
      return {};
    }
  };

  const getElementSize = (el) => ({
    width: Number(el.dataset.width || el.getBoundingClientRect().width),
    height: Number(el.dataset.height || el.getBoundingClientRect().height),
  });

  const getDropPoint = (shelf, event, width, height) => {
    const shelfRect = shelf.getBoundingClientRect();
    const dropX = Math.round(event.clientX - shelfRect.left);
    const dropYFromBottom = Math.round(shelfRect.bottom - event.clientY);
    const clampedX = Math.max(0, Math.min(Math.round(dropX), shelfRect.width - width));
    const clampedY = Math.max(0, Math.min(Math.round(dropYFromBottom - height), shelfRect.height - height));

    return { x: clampedX, y: clampedY, shelfRect };
  };

  const toRect = (x, y, width, height) => ({
    left: x,
    right: x + width,
    bottom: y,
    top: y + height,
  });

  const overlaps = (a, b) => !(
    a.right <= b.left
    || a.left >= b.right
    || a.top <= b.bottom
    || a.bottom >= b.top
  );

  const computeOverlap = (shelf, rect, ignorePlacementId) => Array.from(
    shelf.querySelectorAll('.placed-dish')
  ).some((item) => {
    if (ignorePlacementId && item.dataset.placementId === ignorePlacementId) {
      return false;
    }
    const itemLeft = Number(item.style.left.replace('px', ''));
    const itemBottom = Number(item.style.bottom.replace('px', ''));
    const { width, height } = getElementSize(item);
    const itemRect = toRect(itemLeft, itemBottom, width, height);

    return overlaps(rect, itemRect);
  });

  const isStackCompatible = (targetEl, draggedEl) => (
    targetEl
    && draggedEl
    && targetEl.dataset.isStacked === '1'
    && draggedEl.dataset.isStacked === '1'
    && targetEl.dataset.dishType === draggedEl.dataset.dishType
  );

  const updateCollisionState = (shelf, event) => {
    if (!state.draggedDishEl) {
      clearShelfState(shelf);
      return;
    }

    const targetDishEl = event.target.closest('.placed-dish');
    if (isStackCompatible(targetDishEl, state.draggedDishEl)) {
      setShelfState(shelf, false);
      return;
    }

    const { width, height } = getElementSize(state.draggedDishEl);
    const { x, y } = getDropPoint(shelf, event, width, height);
    const rect = toRect(x, y, width, height);
    const overlap = computeOverlap(shelf, rect, state.draggedPlacementId);

    setShelfState(shelf, overlap);
  };

  const bindDragSource = (item) => {
    item.addEventListener('dragstart', (event) => {
      if (item.dataset.placementId) {
        state.draggedPlacementId = item.dataset.placementId;
        state.draggedDishEl = item;
        event.dataTransfer.setData('text/plain', JSON.stringify({
          placementId: item.dataset.placementId,
        }));
        event.dataTransfer.effectAllowed = 'move';
        return;
      }

      state.draggedDishEl = item;
      event.dataTransfer.setData('text/plain', JSON.stringify({
        dishId: item.dataset.dishId,
      }));
      event.dataTransfer.effectAllowed = 'copy';
    });
    item.addEventListener('dragend', () => {
      state.draggedPlacementId = null;
      state.draggedDishEl = null;
    });
  };

  const createPlacedElement = (sourceDishEl, payload) => {
    const placed = document.createElement('img');
    placed.src = sourceDishEl.dataset.image || sourceDishEl.src;
    placed.alt = sourceDishEl.alt || 'Dish';
    placed.className = 'placed-dish';
    placed.draggable = true;
    placed.dataset.placementId = payload.id;
    placed.dataset.dishId = sourceDishEl.dataset.dishId || payload.dishId;
    placed.dataset.dishType = sourceDishEl.dataset.dishType;
    placed.dataset.isStacked = sourceDishEl.dataset.isStacked;
    placed.dataset.image = placed.src;
    placed.dataset.width = payload.width;
    placed.dataset.height = payload.height;
    placed.style.left = `${payload.x}px`;
    placed.style.bottom = `${payload.y}px`;
    placed.style.width = `${payload.width}px`;
    placed.style.height = `${payload.height}px`;

    return placed;
  };

  const handleStackDrop = async (shelf, targetDishEl) => {
    const targetPlacementId = targetDishEl.dataset.placementId;

    if (state.draggedPlacementId) {
      if (state.draggedPlacementId === targetPlacementId) {
        return true;
      }

      const response = await fetch('/api/stacks/merge', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          sourcePlacementId: Number(state.draggedPlacementId),
          targetPlacementId: Number(targetPlacementId),
          position: 'top',
        }),
      });

      if (!response.ok) {
        setMessage('Failed to stack dish.');
        return true;
      }

      const payload = await response.json();
      const sourcePlacement = payload.placements.find(
        (p) => String(p.id) === String(state.draggedPlacementId)
      );
      if (sourcePlacement && state.draggedDishEl) {
        state.draggedDishEl.style.left = `${sourcePlacement.x}px`;
        state.draggedDishEl.style.bottom = `${sourcePlacement.y}px`;
        shelf.appendChild(state.draggedDishEl);
      }

      setMessage('');
      return true;
    }

    if (!state.draggedDishEl) {
      return true;
    }

    const dishId = state.draggedDishEl.dataset.dishId;
    const response = await fetch(`/api/shelves/${shelf.dataset.shelfId}/placements`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        dishId: Number(dishId),
        targetPlacementId: Number(targetPlacementId),
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
      return true;
    }

    const payload = await response.json();
    const placed = createPlacedElement(state.draggedDishEl, payload);
    shelf.appendChild(placed);
    bindDragSource(placed);
    setMessage('');

    return true;
  };

  const handleMoveDrop = async (shelf, event) => {
    if (!state.draggedPlacementId) {
      return false;
    }

    const placedEl = document.querySelector(`[data-placement-id="${state.draggedPlacementId}"]`);
    if (!placedEl) {
      return true;
    }

    const { width, height } = getElementSize(placedEl);
    const { x, y } = getDropPoint(shelf, event, width, height);

    const response = await fetch(`/api/placements/${state.draggedPlacementId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        x,
        y,
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'Collision detected.' : 'Failed to move dish.');
      return true;
    }

    placedEl.style.left = `${x}px`;
    placedEl.style.bottom = `${y}px`;
    shelf.appendChild(placedEl);
    setMessage('');

    return true;
  };

  const handleCreateDrop = async (shelf, event) => {
    const transferData = parseTransferData(event);
    const dishId = transferData.dishId ? String(transferData.dishId) : '';
    if (!dishId) {
      return false;
    }

    const sourceDishEl = state.draggedDishEl
      || document.querySelector(`.buffet-items [data-dish-id="${dishId}"]`);
    if (!sourceDishEl) {
      return true;
    }

    const response = await fetch(`/api/shelves/${shelf.dataset.shelfId}/placements`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        dishId: Number(dishId),
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to place dish.');
      return true;
    }

    const payload = await response.json();
    const placed = createPlacedElement(sourceDishEl, payload);
    shelf.appendChild(placed);
    bindDragSource(placed);
    setMessage('');

    return true;
  };

  const handleShelfDrop = async (shelf, event) => {
    const transferData = parseTransferData(event);
    if (transferData.placementId) {
      state.draggedPlacementId = String(transferData.placementId);
    } else if (transferData.dishId && !state.draggedDishEl) {
      state.draggedDishEl = document.querySelector(
        `.buffet-items [data-dish-id="${transferData.dishId}"]`
      );
    }

    const targetDishEl = event.target.closest('.placed-dish');
    if (isStackCompatible(targetDishEl, state.draggedDishEl)) {
      await handleStackDrop(shelf, targetDishEl);
      clearShelfState(shelf);
      return;
    }

    if (await handleMoveDrop(shelf, event)) {
      clearShelfState(shelf);
      return;
    }

    await handleCreateDrop(shelf, event);
    clearShelfState(shelf);
  };

  const initTrash = () => {
    if (!trash) {
      return;
    }

    trash.addEventListener('dragover', (event) => {
      if (!state.draggedPlacementId) {
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
      if (!state.draggedPlacementId) {
        return;
      }

      try {
        const response = await fetch(`/api/placements/${state.draggedPlacementId}`, {
          method: 'DELETE',
        });

        if (!response.ok && response.status !== 404) {
          setMessage('Failed to delete dish.');
          return;
        }

        if (response.status === 204) {
          const placedEl = document.querySelector(`[data-placement-id="${state.draggedPlacementId}"]`);
          if (placedEl) {
            placedEl.remove();
          }
        }

        setMessage('');
      } catch (error) {
        setMessage('Failed to delete dish.');
      } finally {
        trash.classList.remove('is-active');
        state.draggedPlacementId = null;
        state.draggedDishEl = null;
      }
    });
  };

  dishItems.forEach((item) => {
    bindDragSource(item);
  });

  shelves.forEach((shelf) => {
    shelf.addEventListener('dragover', (event) => {
      event.preventDefault();
      event.dataTransfer.dropEffect = state.draggedPlacementId ? 'move' : 'copy';
      updateCollisionState(shelf, event);
    });

    shelf.addEventListener('dragleave', (event) => {
      if (!shelf.contains(event.relatedTarget)) {
        clearShelfState(shelf);
      }
    });

    shelf.addEventListener('drop', async (event) => {
      event.preventDefault();
      await handleShelfDrop(shelf, event);
    });
  });

  initTrash();
});
