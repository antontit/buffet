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
    if (transfer.placementId) {
      return { placementId: String(transfer.placementId), dishId: null, elementId: elementId || null };
    }
    if (transfer.dishId) {
      return { placementId: null, dishId: String(transfer.dishId), elementId: null };
    }
    return { placementId: null, dishId: null, elementId: null };
  };

  const getStackItemById = (elementId) => {
    if (!elementId) {
      return null;
    }
    return document.getElementById(elementId);
  };

  const getStackItemByPlacementId = (placementId) => {
    if (!placementId) {
      return null;
    }
    return document.getElementById(`stack-item-${placementId}`);
  };

  const buildStackItemId = (stackId, placementId) => {
    if (stackId) {
      return `stack-item-stack-${stackId}`;
    }
    return `stack-item-${placementId}`;
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
    shelf.querySelectorAll('.stack-item')
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
    const { placementId, dishId, elementId } = getDragData(event);
    if (!placementId && !dishId) {
      clearShelfState(shelf);
      return;
    }

    const targetDishEl = event.target.closest('.stack-item');
    if (targetDishEl && placementId) {
      setShelfState(shelf, false);
      return;
    }

    const draggedEl = placementId
      ? getStackItemById(elementId) || getStackItemById(`stack-item-${placementId}`) || getStackItemById(`stack-item-stack-${placementId}`)
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
    const overlap = computeOverlap(shelf, rect, placementId);

    setShelfState(shelf, overlap);
  };

  const bindDragSource = (item) => {
    item.addEventListener('dragstart', (event) => {
      const dragEl = item.closest('.stack-item') || item;
      if (dragEl.dataset.placementId) {
        event.dataTransfer.setData('text/plain', JSON.stringify({
          placementId: dragEl.dataset.placementId,
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
    wrapper.id = buildStackItemId(payload.stackId || null, payload.id);
    wrapper.dataset.placementId = payload.id;
    wrapper.dataset.dishId = sourceDishEl.dataset.dishId || payload.dishId;
    wrapper.dataset.dishType = sourceDishEl.dataset.dishType;
    wrapper.dataset.isStacked = sourceDishEl.dataset.isStacked;
    wrapper.dataset.image = sourceDishEl.dataset.image || sourceDishEl.src;
    wrapper.dataset.stackId = payload.stackId || '';
    wrapper.dataset.stackIndex = payload.stackIndex || '';
    wrapper.dataset.stackCount = payload.stackCount || '1';
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
    const targetPlacementId = targetDishEl.dataset.placementId;

    if (dragData.placementId) {
      if (dragData.placementId === targetPlacementId) {
        return true;
      }

      const sourceEl = getStackItemById(dragData.elementId) || getStackItemById(`stack-item-${dragData.placementId}`) || getStackItemById(`stack-item-stack-${dragData.placementId}`);
      const sourceStackId = sourceEl?.dataset.stackId || null;
      const response = await fetch('/api/stacks/merge', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          sourcePlacementId: Number(dragData.placementId),
          targetPlacementId: Number(targetPlacementId),
          position: 'top',
        }),
      });

      if (!response.ok) {
        setMessage('Failed to stack dish.');
        return true;
      }

      const payload = await response.json();
      const resolvedStackId = payload.stackId || targetDishEl.dataset.stackId;
      const stackKey = resolvedStackId ? String(resolvedStackId) : '';
      const draggedEl = sourceEl || getStackItemById(`stack-item-${dragData.placementId}`);
      if (draggedEl) {
        draggedEl.remove();
      }
      if (stackKey) {
        const currentCount = getStackCount(stackKey);
        const nextCount = sourceStackId === stackKey ? currentCount : currentCount + 1;
        updateStackUI(stackKey, nextCount);
      }

      setMessage('');
      if (payload.placements) {
        applyStackPayload(payload.placements);
        if (stackKey) {
          updateStackFromPlacements(stackKey, payload.placements);
        }
      }
      if (sourceStackId && resolvedStackId && sourceStackId !== resolvedStackId) {
        updateStackUI(sourceStackId, Math.max(0, getStackCount(sourceStackId) - 1));
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
        targetPlacementId: Number(targetPlacementId),
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
      return true;
    }

    const payload = await response.json();
    const resolvedStackId = payload.stackId || targetDishEl.dataset.stackId;
    setMessage('');
    if (resolvedStackId) {
      targetDishEl.dataset.stackId = String(resolvedStackId);
      targetDishEl.dataset.stackIndex = targetDishEl.dataset.stackIndex || '0';
      targetDishEl.dataset.placementId = String(payload.id);
      if (targetDishEl.id !== `stack-item-stack-${resolvedStackId}`) {
        targetDishEl.id = `stack-item-stack-${resolvedStackId}`;
      }
      updateStackUI(String(resolvedStackId), getStackCount(String(resolvedStackId)) + 1);
    }

    return true;
  };

  const handleMoveDrop = async (shelf, event, dragData) => {
    if (!dragData.placementId) {
      return false;
    }

    const placedEl = getStackItemById(dragData.elementId) || getStackItemById(`stack-item-${dragData.placementId}`) || getStackItemById(`stack-item-stack-${dragData.placementId}`);
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
      updateStackFromPlacements(String(stackId), payload.placements);

      return true;
    }

    const response = await fetch(`/api/placements/${dragData.placementId}`, {
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
    setMessage('');
    if (payload.stackId) {
      const stackId = String(payload.stackId);
      const existingStackEl = getStackItemById(`stack-item-stack-${stackId}`);
      if (existingStackEl) {
        updateStackUI(stackId, getStackCount(stackId) + 1);
        return true;
      }
    }

    const placed = createPlacedElement(sourceDishEl, payload);
    shelf.appendChild(placed);
    bindDragSource(placed);
    if (payload.stackId) {
      updateStackUI(String(payload.stackId));
    }

    return true;
  };

  const handleShelfDrop = async (shelf, event) => {
    const dragData = getDragData(event);
    const targetDishEl = event.target.closest('.stack-item');
    if (targetDishEl && (dragData.placementId || dragData.dishId)) {
      const draggedEl = dragData.placementId
        ? getStackItemById(dragData.elementId) || getStackItemById(`stack-item-${dragData.placementId}`) || getStackItemById(`stack-item-stack-${dragData.placementId}`)
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

    const placementId = placedEl.dataset.placementId;
    if (!placementId) {
      return;
    }
    const stackId = placedEl.dataset.stackId || null;
    const stackCount = stackId ? getStackCount(stackId) : 0;

    try {
      const response = await fetch(
        stackId && stackCount > 1
          ? `/api/stacks/${stackId}`
          : `/api/placements/${placementId}`,
        {
          method: 'DELETE',
        }
      );

      if (!response.ok) {
        setMessage('Failed to delete dish.');
        return;
      }

      if (response.status === 204) {
        if (stackId && stackCount > 1) {
          const stackEl = getStackItemById(`stack-item-stack-${stackId}`);
          if (stackEl) {
            stackEl.remove();
          }
        } else {
          placedEl.remove();
        }
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
      const dragData = getDragData(event);
      event.dataTransfer.dropEffect = dragData.placementId ? 'move' : 'copy';
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
        addButton.style.display = count >= 10 ? 'none' : 'inline-flex';
      }
    }
  };

  const applyStackPayload = (placements) => {
    const updatedStackIds = new Set();
    placements.forEach((placement) => {
      if (placement.stackId) {
        updatedStackIds.add(String(placement.stackId));
      }
    });
    updatedStackIds.forEach((stackId) => {
      updateStackFromPlacements(stackId, placements);
      updateStackUI(stackId);
    });
  };

  const updateStackFromPlacements = (stackId, placements) => {
    if (!stackId) {
      return;
    }
    const items = placements.filter((placement) => String(placement.stackId) === String(stackId));
    if (items.length === 0) {
      return;
    }
    const top = items.reduce((current, candidate) => {
      const currentIndex = Number(current.stackIndex ?? 0);
      const candidateIndex = Number(candidate.stackIndex ?? 0);
      return candidateIndex >= currentIndex ? candidate : current;
    }, items[0]);
    const stackEl = getStackItemById(`stack-item-stack-${stackId}`);
    if (stackEl) {
      stackEl.dataset.placementId = String(top.id);
      stackEl.dataset.stackIndex = String(top.stackIndex ?? '');
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

    const placementId = placedEl.dataset.placementId;
    const dishId = placedEl.dataset.dishId;
    const stackId = placedEl.dataset.stackId || null;
    if (!placementId) {
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
          targetPlacementId: Number(placementId),
        }),
      });

      if (!response.ok) {
        setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
        return;
      }

      const payload = await response.json();
      const resolvedStackId = payload.stackId || placedEl.dataset.stackId;
      if (resolvedStackId) {
        placedEl.dataset.stackId = String(resolvedStackId);
        placedEl.dataset.stackIndex = placedEl.dataset.stackIndex || '0';
        placedEl.dataset.placementId = String(payload.id);
        if (placedEl.id !== `stack-item-stack-${resolvedStackId}`) {
          placedEl.id = `stack-item-stack-${resolvedStackId}`;
        }
        updateStackUI(String(resolvedStackId), getStackCount(String(resolvedStackId)) + 1);
      }
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
      const currentCount = getStackCount(stackKey);
      if (payload.removedId) {
        const removedEl = getStackItemByPlacementId(payload.removedId);
        if (removedEl) {
          removedEl.remove();
        }
      }

      updateStackUI(stackKey, Math.max(0, currentCount - 1));
      if (payload.topId) {
        const stackEl = getStackItemById(`stack-item-stack-${stackKey}`);
        if (stackEl) {
          stackEl.dataset.placementId = String(payload.topId);
        }
      }
      setMessage('');
    }
  };

  document.addEventListener('click', handleDeleteClick);
  document.addEventListener('click', handleStackControls);
});
