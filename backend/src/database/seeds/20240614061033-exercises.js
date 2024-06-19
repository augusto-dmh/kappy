"use strict";

const { faker } = require("@faker-js/faker");

module.exports = {
  up: async (queryInterface, Sequelize) => {
    const exercisesData = Array.from({ length: 100 }, (_, i) => ({
      id: i + 1,
      lesson_id: Math.floor(i / 10) + 1,
      question: faker.lorem.words(3),
      created_at: new Date(),
      updated_at: new Date(),
    }));

    await queryInterface.bulkInsert("exercises", exercisesData, {});
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.bulkDelete("exercises", null, {});
  },
};
