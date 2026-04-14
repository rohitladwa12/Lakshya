-- Batch 7: Data Structures & Design
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- Design (New Category)
('LRU Cache', 'Design', 'Hard',
'Design a data structure that follows the constraints of a Least Recently Used (LRU) cache. 

Implement the LRUCache class:
1. LRUCache(int capacity) Initialize the LRU cache with positive size capacity.
2. int get(int key) Return the value of the key if the key exists, otherwise return -1.
3. void put(int key, int value) Update the value of the key if the key exists. Otherwise, add the key-value pair to the cache. If the number of keys exceeds the capacity, evict the least recently used key.

The functions get and put must each run in O(1) average time complexity.',
'1 <= capacity <= 3000
0 <= key <= 10^4
0 <= value <= 10^5
At most 2 * 10^5 calls will be made to get and put.',
'["LRUCache", "put", "put", "get", "put", "get", "put", "get", "get", "get"]
[[2], [1, 1], [2, 2], [1], [3, 3], [2], [4, 4], [1], [3], [4]]',
'[null, null, null, 1, null, -1, null, -1, 3, 4]',
'To achieve O(1) for both get and put, use a combination of a Doubly Linked List and a Hash Map. The Hash Map provides O(1) access to nodes, while the Doubly Linked List maintains the order of usage (head is most recent, tail is least). When an item is accessed or added, move it to the head. If capacity is exceeded, remove from the tail.',
'O(1)',
'O(capacity)'),

-- Trees (More)
('Validate Binary Search Tree', 'Trees', 'Medium',
'Given the root of a binary tree, determine if it is a valid binary search tree (BST).

A valid BST is defined as follows:
1. The left subtree of a node contains only nodes with keys less than the node\'s key.
2. The right subtree of a node contains only nodes with keys greater than the node\'s key.
3. Both the left and right subtrees must also be binary search trees.',
'The number of nodes in the tree is in the range [1, 10^4].
-2^31 <= Node.val <= 2^31 - 1',
'root = [5,1,4,null,null,3,6]',
'false (4 is at root, but its right child 6 is fine while its left child has 3 in its right subtree - wait, 5 is root, 1 is left, 4 is right. 4\'s left is 3. 3 is less than 4 but also less than 5, but it is in the right subtree of 5, so it must be greater than 5. Invalid.)',
'Recursive approach: Pass a range [min, max] down the tree. For each node, check if min < node.val < max. When moving left, update max = node.val. When moving right, update min = node.val. Alternatively, perform an inorder traversal; for a valid BST, the resulting values must be strictly increasing.',
'O(n)',
'O(h) where h is height'),

('Binary Tree Level Order Traversal', 'Trees', 'Medium',
'Given the root of a binary tree, return the level order traversal of its nodes\' values. (i.e., from left to right, level by level).

Input: root = [3,9,20,null,null,15,7]
Output: [[3],[9,20],[15,7]]',
'The number of nodes in the tree is in the range [0, 2000].
-1000 <= Node.val <= 1000',
'root = [1]',
'[[1]]',
'Use Breadth-First Search (BFS) with a Queue. In each iteration, record the size of the queue (which represents the number of nodes at the current level), then process that many nodes by adding their children to the queue. This captures the tree structure level-by-level.',
'O(n)',
'O(n) for the result and queue'),

-- Design (More)
('Implement Stack using Queues', 'Design', 'Easy',
'Implement a last-in-first-out (LIFO) stack using only two queues. The implemented stack should support all the functions of a normal stack (push, top, pop, and empty).',
'1 <= val <= 9
At most 100 calls will be made to push, pop, top, and empty.',
'["MyStack", "push", "push", "top", "pop", "empty"]
[[], [1], [2], [], [], []]',
'[null, null, null, 2, 2, false]',
'When pushing a new element, add it to the queue, then rotate the queue (dequeuing and enqueuing) n-1 times so the new element is at the front. This makes the front of the queue act like the top of the stack. Alternatively, use two queues and swap them during push or pop.',
'O(n) for push, O(1) for others',
'O(n)'),

('Implement Queue using Stacks', 'Design', 'Easy',
'Implement a first-in-first-out (FIFO) queue using only two stacks. The implemented queue should support all the functions of a normal queue (push, peek, pop, and empty).',
'1 <= val <= 9
At most 100 calls will be made to push, pop, peek, and empty.',
'["MyQueue", "push", "push", "peek", "pop", "empty"]
[[], [1], [2], [], [], []]',
'[null, null, null, 1, 1, false]',
'Use two stacks: `input` and `output`. For `push`, always push to `input`. For `pop` or `peek`, if `output` is empty, transfer all elements from `input` to `output` (this reverses their order). Then operate on `output`. This gives amortized O(1) performance.',
'O(1) amortized',
'O(n)'),

-- Linked Lists (More)
('Intersection of Two Linked Lists', 'Linked Lists', 'Easy',
'Given the heads of two singly linked-lists headA and headB, return the node at which the two lists intersect. If the two linked lists have no intersection at all, return null.',
'The number of nodes of listA is m, listB is n.
1 <= m, n <= 3 * 10^4
1 <= Node.val <= 10^5',
'intersectVal = 8, listA = [4,1,8,4,5], listB = [5,6,1,8,4,5]',
'Intersected at ''8\'\'',
'Two-pointer solution: Initialize two pointers at heads. Move each through its list. When a pointer reaches the end, redirect it to the head of the *other* list. They will eventually meet at the intersection point because both will have traveled the same total distance (m + n).',
'O(m + n)',
'O(1)'),

('Remove Nth Node From End of List', 'Linked Lists', 'Medium',
'Given the head of a linked list, remove the nth node from the end of the list and return its head.',
'The number of nodes in the list is sz.
1 <= sz <= 30
0 <= Node.val <= 100
1 <= n <= sz',
'head = [1,2,3,4,5], n = 2',
'[1,2,3,5]',
'Two-pointer approach: Use a `fast` and `slow` pointer. Move `fast` n steps ahead. Then move both pointers until `fast` reaches the end. The `slow` pointer will now be just before the node to be removed. Use a dummy head to handle the case where the head itself needs to be removed.',
'O(n)',
'O(1)'),

('Copy List with Random Pointer', 'Linked Lists', 'Medium',
'A linked list of length n is given such that each node contains an additional random pointer, which could point to any node in the list, or null. Construct a deep copy of the list.',
'0 <= n <= 1000
-10^4 <= Node.val <= 10^4',
'head = [[7,null],[13,0],[11,4],[10,2],[1,0]]',
'[[7,null],[13,0],[11,4],[10,2],[1,0]]',
'Three-step approach without extra space: 1) Create copy nodes and insert them after original nodes (A -> A'' -> B -> B''). 2) Set random pointers: copyNode.random = originalNode.random.next. 3) Separate the lists to restore original and extract the copy.',
'O(n)',
'O(1) excluding output'),

-- Heaps (More)
('Top K Frequent Words', 'Heaps', 'Medium',
'Given an array of strings words and an integer k, return the k most frequent strings. Return the answer sorted by the frequency from highest to lowest. Words with the same frequency should be sorted by their lexicographical order.',
'1 <= words.length <= 500
1 <= words[i].length <= 10
k is in the range [1, number of unique words]',
'words = ["i","love","leetcode","i","love","coding"], k = 2',
'["i","love"]',
'Use a Hash Map to count frequencies. Then use a Priority Queue (Min-Heap) of size k. To handle lexicographical order for equal frequencies, the heap comparator should favor higher frequency and lower alphabetical order. Finally, reverse the result extracted from the heap.',
'O(n log k)',
'O(n)'),

-- Heaps (Hard)
('Find Median from Data Stream', 'Heaps', 'Hard',
'Design a data structure that supports adding integers from a data stream and finding the median of the integers seen so far.

Implement the MedianFinder class:
1. void addNum(int num) Adds the integer num from the data stream to the data structure.
2. double findMedian() Returns the median of all elements so far.',
'-10^5 <= num <= 10^5
At most 5 * 10^4 calls will be made to addNum and findMedian.',
'["MedianFinder", "addNum", "addNum", "findMedian", "addNum", "findMedian"]
[[], [1], [2], [], [3], []]',
'[null, null, null, 1.5, null, 2.0]',
'Use two Heaps: a Max-Heap for the smaller half of numbers and a Min-Heap for the larger half. Maintain the size property where the max-heap has at most one more element than the min-heap. The median is either the top of the max-heap (if odd size) or the average of both tops.',
'O(log n) to add, O(1) to find',
'O(n)');
