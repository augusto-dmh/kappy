"use strict";

const { faker } = require("@faker-js/faker");

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    const users = Array.from({ length: 10 }, (_, i) => ({
      id: i + 1,
      nickname: faker.word.adjective({ length: { min: 1, max: 20 } }),
      checkpoint: 1,
      created_at: new Date(),
      updated_at: new Date(),
    }));

    await queryInterface.bulkInsert("users", users, {});
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.bulkDelete("users", null, {});
  },
};
