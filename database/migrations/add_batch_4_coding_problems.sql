-- Batch 4: Advanced Topics & Bit Manipulation
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- Bit Manipulation (New Category)
('Single Number', 'Bit Manipulation', 'Easy',
'Given a non-empty array of integers nums, every element appears twice except for one. Find that single one.

Input: [2, 2, 1]
Output: 1',
'1 <= nums.length <= 3 * 10^4
-3 * 10^4 <= nums[i] <= 3 * 10^4',
'nums = [4,1,2,1,2]',
'4',
'Use the XOR operator. A ^ A = 0 and A ^ 0 = A. XORing all elements together will cause all pairs to cancel out, leaving only the single number. This is a brilliant example of using bitwise properties for efficiency.',
'O(n)',
'O(1)'),

('Number of 1 Bits', 'Bit Manipulation', 'Easy',
'Write a function that takes an unsigned integer and returns the number of \'1\' bits it has (also known as the Hamming weight).

Input: 00000000000000000000000000001011
Output: 3',
'The input must be a binary string of length 32',
'n = 11 (binary 1011)',
'3',
'Use bitwise AND with (n-1). The operation n & (n-1) always flips the least significant 1-bit to 0. Count how many times you can perform this until n becomes 0. This is more efficient than checking every bit.',
'O(k) where k is number of 1s',
'O(1)'),

-- Heaps & Queues (New Category)
('Kth Largest Element', 'Heaps', 'Medium',
'Find the kth largest element in an unsorted array. Note that it is the kth largest element in the sorted order, not the kth distinct element.

Input: [3,2,1,5,6,4], k = 2
Output: 5',
'1 <= k <= array.length <= 10^4
-10^4 <= nums[i] <= 10^4',
'nums = [3,2,3,1,2,4,5,5,6], k = 4',
'4',
'Use a Min-Heap of size k. Iterate through the array; if the current element is larger than the heap root, replace the root and re-heapify. At the end, the root is the kth largest. This teaches heap applications for top-K problems.',
'O(n log k)',
'O(k)'),

-- Stacks (More)
('Min Stack Implementation', 'Stacks', 'Medium',
'Design a stack that supports push, pop, top, and retrieving the minimum element in constant time.

Methods: push(val), pop(), top(), getMin()',
'-2^31 <= val <= 2^31 - 1
Methods pop, top and getMin will always be called on non-empty stacks.',
'["MinStack","push","push","push","getMin","pop","top","getMin"]
[[],[-2],[0],[-3],[],[],[],[]]',
'[null,null,null,null,-3,null,0,-2]',
'Use two stacks: one for the regular elements and another to track the minimum values. When pushing, if the new value is <= the current minimum, push it to the min-stack too. This shows how to augment data structures for specific needs.',
'O(1) for all ops',
'O(n)'),

-- Graphs (Basic Category)
('Flood Fill', 'Graphs', 'Easy',
'An image is represented by an m x n integer grid. Perform a flood fill starting from (sr, sc) with a new color.

Note: Change the color of the starting pixel and all adjacent pixels of the same original color.',
'm == image.length, n == image[i].length
1 <= m, n <= 50',
'image = [[1,1,1],[1,1,0],[1,0,1]], sr = 1, sc = 1, newColor = 2',
'[[2,2,2],[2,2,0],[2,0,1]]',
'Use Depth First Search (DFS) or Breadth First Search (BFS). Start at the given pixel, change its color, then recursively visit all 4 neighbors that have the original color. This introduces image processing and graph traversal.',
'O(m * n)',
'O(m * n) for recursion stack'),

-- Strings (Advanced)
('Length of Last Word', 'Strings', 'Easy',
'Given a string s consisting of words and spaces, return the length of the last word in the string.

Input: s = "Hello World"
Output: 5',
'1 <= s.length <= 10^4
s consists of English letters and spaces',
's = "   fly me   to   the moon  "',
'4',
'Start from the end of the string. Skip trailing spaces, then count characters until the next space or the start of the string. This is a common string manipulation interview question.',
'O(n)',
'O(1)'),

-- Arrays (Optimization)
('Container With Most Water', 'Arrays', 'Medium',
'Given an array of heights, find two lines that together with the x-axis form a container that holds the most water.

Input: [1,8,6,2,5,4,8,3,7]
Output: 49',
'n == height.length
2 <= n <= 10^5',
'height = [1,8,6,2,5,4,8,3,7]',
'49',
'Two-pointer approach. Place pointers at both ends. Calculate area using the shorter line. Move the pointer pointing to the shorter line inward, as staying there can never produce a larger area. This teaches the greedy two-pointer logic.',
'O(n)',
'O(1)'),

('Product of Array Except Self', 'Arrays', 'Medium',
'Given an integer array nums, return an array answer such that answer[i] is equal to the product of all the elements of nums except nums[i].
You must write an algorithm that runs in O(n) time and without using the division operation.',
'2 <= nums.length <= 10^5
-30 <= nums[i] <= 30',
'nums = [1,2,3,4]',
'[24,12,8,6]',
'Use prefix and suffix products. Calculate the product of all elements to the left of each index, then multiply by the product of all elements to the right. This teaches how to avoid redundant calculations using extra space or multiple passes.',
'O(n)',
'O(1) if output array is not counted'),

-- Math
('Palindrome Number', 'Math', 'Easy',
'Given an integer x, return true if x is a palindrome, and false otherwise. Do not convert the integer to a string.

Input: 121
Output: true',
'-2^31 <= x <= 2^31 - 1',
'121',
'true',
'Reverse the second half of the number and compare it with the first half. To reverse: use num % 10 and num / 10 in a loop. Reversing only half avoids potential overflow of a full 32-bit integer. This is a classic numerical logic problem.',
'O(log n)',
'O(1)'),

-- Matrices
('Search a 2D Matrix', 'Matrices', 'Medium',
'Write an efficient algorithm that searches for a value in an m x n matrix where each row is sorted and the first integer of each row is greater than the last integer of the previous row.

Input: matrix = [[1,3,5,7],[10,11,16,20],[23,30,34,60]], target = 3
Output: true',
'm == matrix.length, n == matrix[0].length
1 <= m, n <= 100',
'target = 3',
'true',
'Treat the 2D matrix as a flat 1D sorted array. The element at 1D index i is matrix[i / n][i % n]. Perform binary search on this virtual 1D array. This demonstrates logical restructuring of data for better complexity.',
'O(log(m * n))',
'O(1)'),

-- Sliding Window (New Category)
('Maximum Average Subarray I', 'Sliding Window', 'Easy',
'Find a contiguous subarray of length k that has the maximum average value and return this value.

Input: nums = [1,12,-5,-6,50,3], k = 4
Output: 12.75',
'n == nums.length
1 <= k <= n <= 10^5',
'nums = [1,12,-5,-6,50,3], k = 4',
'12.75',
'Fixed-size sliding window. Calculate the sum of the first k elements. Then slide the window by adding the next element and subtracting the one that left. Keep track of the maximum sum found. This is the foundation of window techniques.',
'O(n)',
'O(1)'),

-- Intervals
('Merge Intervals', 'Intervals', 'Medium',
'Given an array of intervals, merge all overlapping intervals.

Input: [[1,3],[2,6],[8,10],[15,18]]
Output: [[1,6],[8,10],[15,18]]',
'1 <= intervals.length <= 10^4
intervals[i].length == 2',
'[[1,3],[2,6],[8,10],[15,18]]',
'[[1,6],[8,10],[15,18]]',
'Sort intervals by start time. Iterate through sorted intervals; if the current interval starts before the previous one ends, merge them by updating the end time. If not, add the previous interval to result. This teaches sorting with custom logic.',
'O(n log n)',
'O(n)');
