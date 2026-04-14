-- Additional Coding Problems for Educational Platform
-- Run this after the initial migration to add more problems

-- Easy Problems (7 more)
INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

('Sum of Array Elements', 'Arrays', 'Easy',
'Write a function to calculate the sum of all elements in an array of integers.

Input: An array of integers
Output: The sum of all elements',
'1 <= array length <= 1000
-1000 <= array[i] <= 1000',
'[1, 2, 3, 4, 5]',
'15',
'Array traversal is fundamental. Initialize a sum variable to 0, then iterate through each element adding it to the sum. This demonstrates the accumulator pattern - a common programming technique.',
'O(n)',
'O(1)'),

('Count Vowels in String', 'Strings', 'Easy',
'Write a function to count the number of vowels (a, e, i, o, u) in a given string. Case insensitive.

Input: A string
Output: Count of vowels',
'1 <= string length <= 1000
String contains only alphabetic characters and spaces',
'Hello World',
'3',
'String iteration with conditional counting. Convert to lowercase for case-insensitive comparison. Use a set or array to store vowels for easy checking. This teaches character-by-character processing.',
'O(n)',
'O(1)'),

('Factorial Using Loops', 'Loops', 'Easy',
'Write a function to calculate the factorial of a non-negative integer using loops (not recursion).

Input: A non-negative integer n
Output: n! (factorial of n)',
'0 <= n <= 12',
'5',
'120',
'Factorial is the product of all positive integers up to n. Use a loop to multiply numbers from 1 to n. Start with result = 1, then multiply by each number. Special case: 0! = 1.',
'O(n)',
'O(1)'),

('Fibonacci Series', 'Loops', 'Easy',
'Write a function to generate the first n numbers of the Fibonacci series.

Input: An integer n
Output: Array of first n Fibonacci numbers',
'1 <= n <= 20',
'7',
'[0, 1, 1, 2, 3, 5, 8]',
'Fibonacci series: each number is the sum of the two preceding ones. Start with 0 and 1. Use two variables to track the last two numbers, then calculate the next. This teaches the sliding window pattern.',
'O(n)',
'O(n)'),

('Check Prime Number', 'Loops', 'Easy',
'Write a function to check if a given number is prime.

Input: A positive integer
Output: true if prime, false otherwise',
'2 <= n <= 10000',
'17',
'true',
'A prime number is divisible only by 1 and itself. Check divisibility from 2 to sqrt(n). If any number divides evenly, it\'s not prime. Optimization: only check up to square root because factors come in pairs.',
'O(√n)',
'O(1)'),

('Find Second Largest', 'Arrays', 'Easy',
'Write a function to find the second largest element in an array.

Input: An array of integers
Output: The second largest value',
'2 <= array length <= 1000
All elements are unique',
'[3, 7, 2, 9, 1, 5]',
'7',
'Track two variables: largest and second largest. Iterate through array updating both as needed. If current > largest, shift largest to second largest. If current > second largest but < largest, update second largest.',
'O(n)',
'O(1)'),

('Remove Duplicates from Array', 'Arrays', 'Easy',
'Write a function to remove duplicate elements from an array, keeping only unique values.

Input: An array of integers
Output: Array with duplicates removed',
'1 <= array length <= 1000',
'[1, 2, 2, 3, 4, 4, 5]',
'[1, 2, 3, 4, 5]',
'Use a Set data structure to automatically handle uniqueness, or manually track seen elements. For manual approach: iterate through array, add to result only if not already present. This teaches hash-based deduplication.',
'O(n)',
'O(n)');

-- Medium Problems (7 more)
INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

('Two Sum Problem', 'Arrays', 'Medium',
'Given an array of integers and a target sum, find two numbers that add up to the target.

Input: Array of integers and target sum
Output: Indices of the two numbers',
'2 <= array length <= 1000
Exactly one solution exists',
'[2, 7, 11, 15], target = 9',
'[0, 1]',
'Classic hash map problem. Store each number with its index in a hash map. For each number, check if (target - number) exists in the map. This demonstrates the complement pattern and trading space for time.',
'O(n)',
'O(n)'),

('Binary Search', 'Searching', 'Medium',
'Implement binary search to find the index of a target value in a sorted array.

Input: Sorted array and target value
Output: Index of target, or -1 if not found',
'Array is sorted in ascending order
1 <= array length <= 10000',
'[1, 3, 5, 7, 9, 11], target = 7',
'3',
'Divide and conquer algorithm. Compare target with middle element. If equal, found! If target < middle, search left half. If target > middle, search right half. Repeat until found or range is empty.',
'O(log n)',
'O(1)'),

('Merge Two Sorted Arrays', 'Arrays', 'Medium',
'Merge two sorted arrays into one sorted array.

Input: Two sorted arrays
Output: Single merged sorted array',
'0 <= array length <= 1000
Both arrays sorted in ascending order',
'[1, 3, 5], [2, 4, 6]',
'[1, 2, 3, 4, 5, 6]',
'Two-pointer technique. Use one pointer for each array. Compare elements at both pointers, add smaller to result, move that pointer forward. Continue until both arrays exhausted. This teaches merging sorted sequences.',
'O(n + m)',
'O(n + m)'),

('Longest Substring Without Repeating', 'Strings', 'Medium',
'Find the length of the longest substring without repeating characters.

Input: A string
Output: Length of longest substring',
'0 <= string length <= 5000',
'abcabcbb',
'3',
'Sliding window with hash set. Expand window by moving right pointer, adding characters to set. When duplicate found, shrink window from left until duplicate removed. Track maximum window size seen.',
'O(n)',
'O(min(n, m))'),

('Valid Parentheses', 'Strings', 'Medium',
'Check if a string of parentheses is valid (properly opened and closed).

Input: String containing (, ), {, }, [, ]
Output: true if valid, false otherwise',
'1 <= string length <= 1000
String contains only brackets',
'({[]})',
'true',
'Stack-based validation. Push opening brackets onto stack. For closing bracket, check if stack top matches. If stack empty or mismatch, invalid. At end, stack should be empty. This teaches stack applications.',
'O(n)',
'O(n)'),

('Rotate Array', 'Arrays', 'Medium',
'Rotate an array to the right by k steps.

Input: Array and number of steps k
Output: Rotated array',
'1 <= array length <= 1000
0 <= k <= 1000',
'[1, 2, 3, 4, 5], k = 2',
'[4, 5, 1, 2, 3]',
'Three-step reversal: 1) Reverse entire array, 2) Reverse first k elements, 3) Reverse remaining elements. This in-place rotation demonstrates array manipulation without extra space.',
'O(n)',
'O(1)'),

('Find Missing Number', 'Arrays', 'Medium',
'Given an array containing n distinct numbers from 0 to n, find the missing number.

Input: Array of n integers
Output: The missing number',
'1 <= n <= 10000
Array contains n distinct numbers in range [0, n]',
'[3, 0, 1]',
'2',
'Mathematical approach: sum of 0 to n is n*(n+1)/2. Subtract sum of array elements to find missing number. Alternative: XOR all numbers 0 to n with all array elements - duplicates cancel out, leaving missing number.',
'O(n)',
'O(1)');

-- Hard Problems (2 more)
INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

('Longest Common Subsequence', 'Strings', 'Hard',
'Find the length of the longest common subsequence between two strings.

Input: Two strings
Output: Length of LCS',
'1 <= string length <= 1000',
'ABCDGH, AEDFHR',
'3',
'Dynamic Programming classic. Build a 2D table where dp[i][j] = LCS length of first i chars of string1 and first j chars of string2. If chars match, dp[i][j] = dp[i-1][j-1] + 1. Else, max of dp[i-1][j] and dp[i][j-1].',
'O(n * m)',
'O(n * m)'),

('0/1 Knapsack Problem', 'Arrays', 'Hard',
'Given weights and values of items, find maximum value that can fit in a knapsack of capacity W.

Input: Arrays of weights, values, and capacity W
Output: Maximum value achievable',
'1 <= n <= 100
1 <= W <= 1000',
'weights = [1, 2, 3], values = [6, 10, 12], W = 5',
'22',
'Dynamic Programming optimization problem. dp[i][w] = max value using first i items with capacity w. For each item: either include it (value[i] + dp[i-1][w-weight[i]]) or exclude it (dp[i-1][w]). Take maximum.',
'O(n * W)',
'O(n * W)');
