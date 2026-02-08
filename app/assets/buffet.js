document.addEventListener('DOMContentLoaded', () => {
  const dishItems = document.querySelectorAll('[data-dish-id]');
  const shelves = document.querySelectorAll('.shelf[data-shelf-id]');
  const message = document.querySelector('.buffet-message');
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
    const clampedY = 0;

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
    const wrapper = document.createElement('div');
    wrapper.className = 'placed-dish';
    wrapper.draggable = true;
    wrapper.dataset.placementId = payload.id;
    wrapper.dataset.dishId = sourceDishEl.dataset.dishId || payload.dishId;
    wrapper.dataset.dishType = sourceDishEl.dataset.dishType;
    wrapper.dataset.isStacked = sourceDishEl.dataset.isStacked;
    wrapper.dataset.image = sourceDishEl.dataset.image || sourceDishEl.src;
    wrapper.dataset.width = payload.width;
    wrapper.dataset.height = payload.height;
    wrapper.style.left = `${payload.x}px`;
    wrapper.style.bottom = `${payload.y}px`;
    wrapper.style.width = `${payload.width}px`;
    wrapper.style.height = `${payload.height}px`;

    const img = document.createElement('img');
    img.src = wrapper.dataset.image;
    img.alt = sourceDishEl.alt || 'Dish';
    wrapper.appendChild(img);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'placed-delete';
    button.setAttribute('aria-label', 'Delete dish');
    button.textContent = '×';
    wrapper.appendChild(button);

    return wrapper;
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
    const response = await fetch(`/api/stacks/${targetPlacementId}/add`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        dishId: Number(dishId),
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

    const { width, height } = getElementSize(sourceDishEl);
    const { x } = getDropPoint(shelf, event, width, height);

    const response = await fetch(`/api/shelves/${shelf.dataset.shelfId}/placements`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        dishId: Number(dishId),
        x,
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

  const handleDeleteClick = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('placed-delete')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const placedEl = target.closest('.placed-dish');
    if (!placedEl || !(placedEl instanceof HTMLElement)) {
      return;
    }

    const placementId = placedEl.dataset.placementId;
    if (!placementId) {
      return;
    }

    try {
      const response = await fetch(`/api/placements/${placementId}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        setMessage('Failed to delete dish.');
        return;
      }

      if (response.status === 204) {
        placedEl.remove();
        setMessage('');
      }
    } catch (error) {
      setMessage('Failed to delete dish.');
    }
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

  document.addEventListener('click', handleDeleteClick);
});
