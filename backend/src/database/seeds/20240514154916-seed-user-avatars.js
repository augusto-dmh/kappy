"use strict";

const { faker } = require("@faker-js/faker");

module.exports = {
  up: async (queryInterface, Sequelize) => {
    const userAvatarsData = [];
    const usedCombinations = new Set();

    while (userAvatarsData.length < 100) {
      const userId = faker.number.int({ min: 1, max: 50 });
      const avatarId = faker.number.int({ min: 1, max: 10 });

      const combination = `${userId}-${avatarId}`;

      if (!usedCombinations.has(combination)) {
        userAvatarsData.push({
          user_id: userId,
          avatar_id: avatarId,
          created_at: new Date(),
          updated_at: new Date(),
        });

        usedCombinations.add(combination);
      }
    }

    return queryInterface.bulkInsert("user_avatars", userAvatarsData);
  },

  down: async (queryInterface, Sequelize) => {
    return queryInterface.bulkDelete("user_avatars", null, {});
  },
};
