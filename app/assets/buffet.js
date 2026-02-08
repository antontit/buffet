document.addEventListener('DOMContentLoaded', () => {
  const dishItems = document.querySelectorAll('[data-dish-id]');
  const shelves = document.querySelectorAll('.shelf[data-shelf-id]');
  const message = document.querySelector('.buffet-message');

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

  const getDragData = (event) => {
    const transfer = parseTransferData(event);
    const elementId = event.dataTransfer.getData('text/stack-item-id');
    if (transfer.stackId) {
      return { stackId: String(transfer.stackId), dishId: null, elementId: elementId || null };
    }
    if (transfer.dishId) {
      return { stackId: null, dishId: String(transfer.dishId), elementId: null };
    }
    return { stackId: null, dishId: null, elementId: null };
  };

  const getStackItemById = (elementId) => {
    if (!elementId) {
      return null;
    }
    return document.getElementById(elementId);
  };

  const buildStackItemId = (stackId) => `stack-item-stack-${stackId}`;

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

  const computeOverlap = (shelf, rect, ignoreStackId) => Array.from(
    shelf.querySelectorAll('.stack-item')
  ).some((item) => {
    if (ignoreStackId && item.dataset.stackId === ignoreStackId) {
      return false;
    }
      const itemLeft = Number(item.style.left.replace('px', ''));
      const itemBottom = Number(item.style.bottom.replace('px', ''));
    const { width, height } = getElementSize(item);
    const itemRect = toRect(itemLeft, itemBottom, width, height);

    return overlaps(rect, itemRect);
  });

  const isStackCompatible = (targetEl, draggedEl) => {
    if (!targetEl || !draggedEl) {
      return false;
    }
    const targetLimit = Number(targetEl.dataset.stackLimit || 1);
    const draggedLimit = Number(draggedEl.dataset.stackLimit || 1);
    return targetLimit > 1 && draggedLimit > 1 && targetEl.dataset.dishType === draggedEl.dataset.dishType;
  };

  const updateCollisionState = (shelf, event) => {
    const { stackId, dishId, elementId } = getDragData(event);
    if (!stackId && !dishId) {
      clearShelfState(shelf);
      return;
    }

    const targetDishEl = event.target.closest('.stack-item');
    if (targetDishEl && stackId) {
      setShelfState(shelf, false);
      return;
    }

    const draggedEl = stackId
      ? getStackItemById(elementId) || getStackItemById(buildStackItemId(stackId))
      : document.querySelector(`.buffet-items [data-dish-id="${dishId}"]`);
    if (!draggedEl) {
      clearShelfState(shelf);
      return;
    }

    if (isStackCompatible(targetDishEl, draggedEl)) {
      setShelfState(shelf, false);
      return;
    }

    const { width, height } = getElementSize(draggedEl);
    const { x, y } = getDropPoint(shelf, event, width, height);
    const rect = toRect(x, y, width, height);
    const overlap = computeOverlap(shelf, rect, stackId);

    setShelfState(shelf, overlap);
  };

  const bindDragSource = (item) => {
    item.addEventListener('dragstart', (event) => {
      const dragEl = item.closest('.stack-item') || item;
      if (dragEl.dataset.stackId) {
        event.dataTransfer.setData('text/plain', JSON.stringify({
          stackId: dragEl.dataset.stackId,
        }));
        event.dataTransfer.setData('text/stack-item-id', dragEl.id || '');
        event.dataTransfer.effectAllowed = 'move';
        return;
      }

      event.dataTransfer.setData('text/plain', JSON.stringify({
        dishId: dragEl.dataset.dishId,
      }));
      event.dataTransfer.effectAllowed = 'copy';
    });
  };

  const createPlacedElement = (sourceDishEl, payload) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'stack-item';
    wrapper.draggable = true;
    wrapper.id = buildStackItemId(payload.id);
    wrapper.dataset.stackId = payload.id;
    wrapper.dataset.dishId = sourceDishEl.dataset.dishId || payload.dishId;
    wrapper.dataset.dishType = sourceDishEl.dataset.dishType;
    wrapper.dataset.stackLimit = sourceDishEl.dataset.stackLimit || '1';
    wrapper.dataset.image = sourceDishEl.dataset.image || sourceDishEl.src;
    wrapper.dataset.stackCount = payload.count || '1';
    wrapper.dataset.width = payload.width;
    wrapper.dataset.height = payload.height;
    wrapper.style.left = `${payload.x}px`;
    wrapper.style.bottom = `${payload.y}px`;
    wrapper.style.width = `${payload.width}px`;
    wrapper.style.height = `${payload.height}px`;

    const dishWrap = document.createElement('div');
    dishWrap.className = 'placed-dish';
    const img = document.createElement('img');
    img.src = wrapper.dataset.image;
    img.alt = sourceDishEl.alt || 'Dish';
    dishWrap.appendChild(img);
    wrapper.appendChild(dishWrap);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'placed-delete';
    button.setAttribute('aria-label', 'Delete dish');
    button.setAttribute('draggable', 'false');
    button.textContent = '×';
    wrapper.appendChild(button);

    const badge = document.createElement('div');
    badge.className = 'stack-badge';
    badge.dataset.stackBadge = '1';
    badge.style.display = 'none';
    wrapper.appendChild(badge);

    const controls = document.createElement('div');
    controls.className = 'stack-controls';
    controls.dataset.stackControls = '1';
    controls.style.display = 'none';
    controls.innerHTML = '<button type="button" class="stack-add" aria-label="Add to stack" draggable="false">+</button><button type="button" class="stack-remove" aria-label="Remove from stack" draggable="false">-</button>';
    wrapper.appendChild(controls);

    return wrapper;
  };

  const handleStackDrop = async (shelf, targetDishEl, dragData) => {
    const targetStackId = targetDishEl.dataset.stackId;

    if (dragData.stackId) {
      if (dragData.stackId === targetStackId) {
        return true;
      }

      const sourceEl = getStackItemById(dragData.elementId) || getStackItemById(buildStackItemId(dragData.stackId));
      const sourceStackId = sourceEl?.dataset.stackId || null;
      const sourceCount = sourceStackId ? getStackCount(sourceStackId) : 1;
      const response = await fetch('/api/stacks/merge', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          sourceStackId: Number(dragData.stackId),
          targetStackId: Number(targetStackId),
        }),
      });

      if (!response.ok) {
        setMessage('Failed to stack dish.');
        return true;
      }

      const payload = await response.json();
      const stackKey = targetDishEl.dataset.stackId ? String(targetDishEl.dataset.stackId) : '';
      const movedCount = Number(payload.movedCount || 0);
      const targetCount = Number(payload.targetCount || getStackCount(stackKey));
      const sourceRemainingCount = Number(payload.sourceRemainingCount ?? sourceCount);
      const draggedEl = sourceEl || getStackItemById(buildStackItemId(dragData.stackId));

      if (sourceStackId && sourceStackId !== stackKey) {
        if (sourceRemainingCount === 0 && draggedEl) {
          draggedEl.remove();
        } else if (sourceRemainingCount > 0) {
          updateStackUI(sourceStackId, sourceRemainingCount);
        }
      } else if (draggedEl && sourceStackId !== stackKey) {
        draggedEl.remove();
      }

      if (stackKey) {
        updateStackUI(stackKey, targetCount);
      }

      setMessage('');
      if (stackKey) {
        updateStackUI(stackKey);
      }
      return true;
    }

    if (!dragData.dishId) {
      return true;
    }

    const dishId = dragData.dishId;
    const response = await fetch('/api/stacks/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        dishId: Number(dishId),
        targetStackId: Number(targetStackId),
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
      return true;
    }

    const payload = await response.json();
    const stackId = targetDishEl.dataset.stackId;
    setMessage('');
    if (stackId) {
      updateStackUI(String(stackId), Number(payload.count || getStackCount(String(stackId)) + 1));
    }

    return true;
  };

  const handleMoveDrop = async (shelf, event, dragData) => {
    if (!dragData.stackId) {
      return false;
    }

    const placedEl = getStackItemById(dragData.elementId) || getStackItemById(buildStackItemId(dragData.stackId));
    if (!placedEl) {
      return true;
    }

    const { width, height } = getElementSize(placedEl);
    const { x, y } = getDropPoint(shelf, event, width, height);
    const stackId = placedEl.dataset.stackId || null;
    const stackCount = stackId ? getStackCount(stackId) : 0;
    if (stackId && stackCount > 1) {
      const response = await fetch(`/api/stacks/${stackId}`, {
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
        setMessage(response.status === 409 ? 'Collision detected.' : 'Failed to move stack.');
        return true;
      }

      const payload = await response.json();
      placedEl.style.left = `${x}px`;
      placedEl.style.bottom = `${y}px`;
      shelf.appendChild(placedEl);
      setMessage('');
      updateStackUI(stackId);

      return true;
    }

    const response = await fetch(`/api/stacks/${dragData.stackId}`, {
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

  const handleCreateDrop = async (shelf, event, dragData) => {
    const dishId = dragData.dishId ? String(dragData.dishId) : '';
    if (!dishId) {
      return false;
    }

    const sourceDishEl = document.querySelector(`.buffet-items [data-dish-id="${dishId}"]`);
    if (!sourceDishEl) {
      return true;
    }

    const { width, height } = getElementSize(sourceDishEl);
    const { x } = getDropPoint(shelf, event, width, height);

    const response = await fetch(`/api/shelves/${shelf.dataset.shelfId}/stacks`, {
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
    setMessage('');
    if (payload.id) {
      const stackId = String(payload.id);
      const existingStackEl = getStackItemById(`stack-item-stack-${stackId}`);
      if (existingStackEl) {
        updateStackUI(stackId, getStackCount(stackId) + 1);
        return true;
      }
    }

    const placed = createPlacedElement(sourceDishEl, payload);
    shelf.appendChild(placed);
    bindDragSource(placed);
    if (payload.id) {
      updateStackUI(String(payload.id), Number(payload.count || 1));
    }

    return true;
  };

  const handleShelfDrop = async (shelf, event) => {
    const dragData = getDragData(event);
    const targetDishEl = event.target.closest('.stack-item');
    if (targetDishEl && (dragData.stackId || dragData.dishId)) {
      const draggedEl = dragData.stackId
        ? getStackItemById(dragData.elementId) || getStackItemById(buildStackItemId(dragData.stackId))
        : document.querySelector(`.buffet-items [data-dish-id="${dragData.dishId}"]`);
      if (isStackCompatible(targetDishEl, draggedEl)) {
        await handleStackDrop(shelf, targetDishEl, dragData);
        clearShelfState(shelf);
        return;
      }
    }

    if (await handleMoveDrop(shelf, event, dragData)) {
      clearShelfState(shelf);
      return;
    }

    await handleCreateDrop(shelf, event, dragData);
    clearShelfState(shelf);
  };

  const handleDeleteClick = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('placed-delete')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const placedEl = target.closest('.stack-item');
    if (!placedEl || !(placedEl instanceof HTMLElement)) {
      return;
    }

    const stackId = placedEl.dataset.stackId || null;
    if (!stackId) {
      return;
    }

    try {
      const response = await fetch(`/api/stacks/${stackId}`, {
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

  document.querySelectorAll('.stack-item').forEach((item) => {
    bindDragSource(item);
  });

  shelves.forEach((shelf) => {
    shelf.addEventListener('dragover', (event) => {
      event.preventDefault();
      const dragData = getDragData(event);
      event.dataTransfer.dropEffect = dragData.stackId ? 'move' : 'copy';
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

  const getStackCount = (stackId) => {
    if (!stackId) {
      return 0;
    }
    const stackEl = getStackItemById(`stack-item-stack-${stackId}`);
    if (!stackEl) {
      return 0;
    }
    const stored = Number(stackEl.dataset.stackCount || 0);
    return stored > 0 ? stored : 1;
  };

  const updateStackUI = (stackId, countOverride = null) => {
    if (!stackId) {
      return;
    }
    const stackEl = getStackItemById(`stack-item-stack-${stackId}`);
    if (!stackEl) {
      return;
    }

    const stored = Number(stackEl.dataset.stackCount || 0);
    const count = countOverride !== null ? countOverride : (stored > 0 ? stored : 1);
    const badge = stackEl.querySelector('[data-stack-badge]');
    const controls = stackEl.querySelector('[data-stack-controls]');
    stackEl.dataset.stackCount = String(count);
    if (badge) {
      badge.textContent = String(count);
      badge.style.display = count > 1 ? 'flex' : 'none';
    }
    if (controls) {
      controls.style.display = count > 1 ? 'flex' : 'none';
      const addButton = controls.querySelector('.stack-add');
      if (addButton) {
        const limit = Number(stackEl.dataset.stackLimit || 1);
        addButton.style.display = count >= limit ? 'none' : 'inline-flex';
      }
    }
  };

  const handleStackControls = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (!target.classList.contains('stack-add') && !target.classList.contains('stack-remove')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const placedEl = target.closest('.stack-item');
    if (!placedEl) {
      return;
    }

    const dishId = placedEl.dataset.dishId;
    const stackId = placedEl.dataset.stackId || null;
    if (!stackId) {
      return;
    }

    if (target.classList.contains('stack-add')) {
      const response = await fetch('/api/stacks/add', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          dishId: Number(dishId),
          targetStackId: Number(stackId),
        }),
      });

      if (!response.ok) {
        setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
        return;
      }

      const payload = await response.json();
      updateStackUI(String(stackId), Number(payload.count || getStackCount(String(stackId)) + 1));
      setMessage('');
      return;
    }

    if (target.classList.contains('stack-remove')) {
      if (!stackId) {
        setMessage('Stack not found.');
        return;
      }

      const response = await fetch('/api/stacks/unstack', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          stackId: Number(stackId),
        }),
      });

      if (!response.ok) {
        setMessage('Failed to remove from stack.');
        return;
      }

      const payload = await response.json();
      const stackKey = String(payload.stackId);
      if (payload.deleted) {
        const stackEl = getStackItemById(`stack-item-stack-${stackKey}`);
        if (stackEl) {
          stackEl.remove();
        }
      } else {
        updateStackUI(stackKey, Number(payload.remainingCount || Math.max(0, getStackCount(stackKey) - 1)));
      }
      setMessage('');
    }
  };

  document.addEventListener('click', handleDeleteClick);
  document.addEventListener('click', handleStackControls);
});
